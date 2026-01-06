# Performance Issues for KISS - Faster Customer & Order Search

GitHub Repo: https://github.com/kissplugins/KISS-woo-fast-search

---

## Issue 1: N+1 Query Problem - 100+ Queries Per Search

**Labels:** `bug`, `performance`, `priority-critical`

### Description

The `search_customers()` method executes 100+ database queries for a single search due to N+1 query pattern in the results loop.

### Current Behavior

For each of the 20 customers returned, the loop executes:
- 3x `get_user_meta()` calls (redundant - meta already loaded)
- 1x `get_order_count_for_customer()` query
- 1x `wc_get_orders()` call in `get_recent_orders_for_customer()`

**Total: ~100 queries per search**

### Location

`includes/class-kiss-woo-search.php` lines 50-77

```php
foreach ( $users as $user ) {
    $first = get_user_meta( $user_id, 'billing_first_name', true );  // Redundant
    $last  = get_user_meta( $user_id, 'billing_last_name', true );   // Redundant
    $billing_email = get_user_meta( $user_id, 'billing_email', true ); // Redundant

    $order_count = $this->get_order_count_for_customer( $user_id );  // 1 query per user

    'orders_list' => $this->get_recent_orders_for_customer( ... )    // 1 query per user
}
```

### Impact

- Search takes 30-60 seconds under server load
- Database connection pool exhaustion
- Poor customer service response times

### Recommended Fix

1. Remove redundant `get_user_meta()` calls - data is already available from `all_with_meta`
2. Batch order queries: fetch all orders for all customer IDs in ONE query, then group in PHP

```php
// Batch approach
$all_customer_ids = wp_list_pluck( $users, 'ID' );
$all_orders = wc_get_orders([
    'customer' => $all_customer_ids,
    'limit'    => 200,
    'orderby'  => 'date',
    'order'    => 'DESC',
]);
// Group by customer_id in PHP
```

### Estimated Impact

Should reduce queries from ~100 to ~3-5 per search.

---

## Issue 2: Expensive Meta Query with OR + LIKE Causes Full Table Scans

**Labels:** `bug`, `performance`, `priority-critical`

### Description

The customer search uses a `meta_query` with `OR` relation and `LIKE` comparisons, which generates SQL that cannot use indexes and forces full table scans on `wp_usermeta`.

### Current Behavior

```php
'meta_query' => array(
    'relation' => 'OR',
    array(
        'key'     => 'billing_email',
        'value'   => $term,
        'compare' => 'LIKE',  // Becomes %term% - no index
    ),
    array(
        'key'     => 'billing_first_name',
        'value'   => $term,
        'compare' => 'LIKE',
    ),
    array(
        'key'     => 'billing_last_name',
        'value'   => $term,
        'compare' => 'LIKE',
    ),
),
```

### Location

`includes/class-kiss-woo-search.php` lines 25-42

### Generated SQL (approximate)

```sql
SELECT * FROM wp_users
INNER JOIN wp_usermeta AS mt1 ON wp_users.ID = mt1.user_id
INNER JOIN wp_usermeta AS mt2 ON wp_users.ID = mt2.user_id
INNER JOIN wp_usermeta AS mt3 ON wp_users.ID = mt3.user_id
WHERE (user_email LIKE '%term%' OR ...)
AND (
    (mt1.meta_key = 'billing_email' AND mt1.meta_value LIKE '%term%')
    OR (mt2.meta_key = 'billing_first_name' AND mt2.meta_value LIKE '%term%')
    OR (mt3.meta_key = 'billing_last_name' AND mt3.meta_value LIKE '%term%')
)
```

### Impact

- Full table scan on `wp_usermeta` (millions of rows on large sites)
- Multiple JOINs compound the problem
- Query time grows linearly with user count

### Recommended Fix

Option A: Use direct SQL with UNION (faster than OR):
```php
global $wpdb;
$sql = $wpdb->prepare("
    SELECT DISTINCT user_id FROM {$wpdb->usermeta}
    WHERE meta_key = 'billing_email' AND meta_value LIKE %s
    UNION
    SELECT DISTINCT user_id FROM {$wpdb->usermeta}
    WHERE meta_key = 'billing_first_name' AND meta_value LIKE %s
    UNION
    SELECT DISTINCT user_id FROM {$wpdb->usermeta}
    WHERE meta_key = 'billing_last_name' AND meta_value LIKE %s
    LIMIT 20
", $term . '%', $term . '%', $term . '%');
```

Option B: For exact email searches, use direct lookup:
```php
if ( is_email( $term ) ) {
    $user = get_user_by( 'email', $term );  // Direct index lookup
}
```

---

## Issue 3: `fields => 'all_with_meta'` Loads Excessive Data

**Labels:** `performance`, `priority-high`

### Description

The user query requests `fields => 'all_with_meta'` which loads ALL metadata for every matched user, consuming excessive memory and I/O.

### Current Behavior

```php
$user_query_args = array(
    'number' => 20,
    'fields' => 'all_with_meta',  // Loads ALL meta for each user
    // ...
);
```

### Location

`includes/class-kiss-woo-search.php` line 20

### Impact

- Each user may have 50+ meta entries (WooCommerce adds many)
- Loading 20 users = 1000+ meta rows fetched
- High memory usage, slow serialization

### Recommended Fix

Fetch only user IDs, then batch-load only required meta:

```php
$user_query_args = array(
    'number' => 20,
    'fields' => 'ID',  // Just IDs
    // ...
);

$user_ids = $user_query->get_results();

// Batch load only needed meta
$meta_keys = ['billing_first_name', 'billing_last_name', 'billing_email'];
// Use single query to get all needed meta for all users
```

---

## Issue 4: Leading Wildcard Search Prevents Index Usage

**Labels:** `performance`, `priority-medium`

### Description

The search uses leading wildcard `*term*` which prevents MySQL from using indexes on the users table.

### Current Behavior

```php
'search' => '*' . esc_attr( $term ) . '*',
```

### Location

`includes/class-kiss-woo-search.php` line 23

### Impact

- MySQL cannot use index on `user_email`, `user_login`, `display_name`
- Forces full table scan on `wp_users`

### Recommended Fix

For email searches, check if it's a complete email and use exact match:
```php
if ( is_email( $term ) ) {
    'search' => $term,  // Exact match, uses index
} else {
    'search' => $term . '*',  // Trailing wildcard only, can use index
}
```

Note: Trailing wildcard `term%` CAN use indexes. Leading wildcard `%term` cannot.

---

## Issue 5: Incorrect Escaping Function Used

**Labels:** `bug`, `priority-low`

### Description

`esc_attr()` is used for a SQL search context, which is incorrect. While not a security issue (WordPress handles escaping internally), it's semantically wrong and could mangle certain search terms.

### Current Behavior

```php
'search' => '*' . esc_attr( $term ) . '*',
```

### Location

`includes/class-kiss-woo-search.php` line 23

### Recommended Fix

Remove `esc_attr()` - WordPress `WP_User_Query` handles escaping internally:
```php
'search' => '*' . $term . '*',
```

The `$term` is already sanitized via `sanitize_text_field()` in the AJAX handler.

---

## Issue 6: Order Queries Not Batched Across Customers

**Labels:** `performance`, `priority-high`

### Description

`get_recent_orders_for_customer()` is called once per customer, resulting in 20 separate `wc_get_orders()` queries.

### Current Behavior

```php
// Called 20 times in the loop
protected function get_recent_orders_for_customer( $user_id, $email ) {
    $orders = wc_get_orders( $args );
    // ...
}
```

### Location

`includes/class-kiss-woo-search.php` lines 186-214

### Impact

- 20 database round-trips per search
- Each query has overhead (parsing, optimization, execution)

### Recommended Fix

Fetch all orders for all matched customers in one query:

```php
// After getting customer IDs
$all_customer_ids = wp_list_pluck( $users, 'ID' );

$all_orders = wc_get_orders([
    'customer' => $all_customer_ids,
    'limit'    => -1,  // Or cap at 200
    'orderby'  => 'date',
    'order'    => 'DESC',
]);

// Group orders by customer_id in PHP
$orders_by_customer = [];
foreach ( $all_orders as $order ) {
    $cid = $order->get_customer_id();
    if ( ! isset( $orders_by_customer[ $cid ] ) ) {
        $orders_by_customer[ $cid ] = [];
    }
    if ( count( $orders_by_customer[ $cid ] ) < 10 ) {
        $orders_by_customer[ $cid ][] = $order;
    }
}
```

---

## Summary Table

| Issue | Priority | Impact | Estimated Effort |
|-------|----------|--------|------------------|
| #1 N+1 Query Problem | Critical | 30-60s query times | Medium |
| #2 Meta Query Full Table Scan | Critical | Slow on large user tables | Medium |
| #3 Excessive Meta Loading | High | Memory/IO overhead | Low |
| #4 Leading Wildcard | Medium | Index bypass | Low |
| #5 Wrong Escaping Function | Low | Semantic issue | Trivial |
| #6 Non-Batched Order Queries | High | 20 extra round-trips | Medium |

---

## Suggested Implementation Order

1. **Issue #1** - Remove redundant `get_user_meta()` calls (quick win)
2. **Issue #6** - Batch order queries (significant improvement)
3. **Issue #3** - Change to `fields => 'ID'` with batched meta loading
4. **Issue #2** - Optimize meta query with UNION or direct SQL
5. **Issue #4** - Use trailing wildcard where possible
6. **Issue #5** - Remove `esc_attr()` wrapper
