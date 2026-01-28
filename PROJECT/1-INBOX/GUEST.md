# Guest vs Customer Orders - Logic Analysis
**Date:** 2026-01-06  
**Issue:** Potential filtering gap in order display logic

---

## Current Implementation

### 1. Customer Order Fetching (`get_recent_orders_for_customers()`)
**Location:** `includes/class-kiss-woo-search.php`, lines 648-694

**Query Parameters:**
```php
wc_get_orders([
    'customer' => $user_ids,  // Searches by customer_id field
    'limit'    => count($user_ids) * 10,
    'orderby'  => 'date',
    'order'    => 'DESC',
    'status'   => array_keys(wc_get_order_statuses()),
]);
```

**Filtering:**
- Line 680: Gets `customer_id` from each order
- Line 682: Skips orders where `customer_id` is empty OR not in the requested user IDs
- **Result:** Only returns orders where `customer_id` matches a registered user

---

### 2. Guest Order Fetching (`search_guest_orders_by_email()`)
**Location:** `includes/class-kiss-woo-search.php`, lines 703-734

**Query Parameters:**
```php
wc_get_orders([
    'billing_email' => $term,  // Searches by billing email
    'limit'         => 20,
    'orderby'       => 'date',
    'order'         => 'DESC',
    'status'        => array_keys(wc_get_order_statuses()),
]);
```

**Filtering:**
- Line 708: Only runs if `$term` is a valid email
- Line 728: Only includes orders where `customer_id === 0` (true guest orders)
- **Result:** Only returns orders with NO customer account

---

## The Issue

### Scenario 1: Registered Customer with Multiple Emails ⚠️

**Setup:**
- Customer account: User ID `123`, email `john@personal.com`
- Order #1: Placed while logged in, billing email `john@work.com`, customer_id = `123`
- Order #2: Placed while logged in, billing email `john@personal.com`, customer_id = `123`

**Search by `john@work.com`:**
1. ✅ **Customer search** finds the user IF `billing_email` meta = `john@work.com`
2. ✅ **Customer orders** shows both orders (because customer_id = 123)
3. ❌ **Guest orders** shows nothing (correctly, because customer_id ≠ 0)

**Search by `john@personal.com`:**
1. ✅ **Customer search** finds the user (account email matches)
2. ✅ **Customer orders** shows both orders
3. ❌ **Guest orders** shows nothing (correctly)

**Potential Problem:**
- If customer's `billing_email` meta doesn't match the search term, the customer might not be found
- But this is actually handled by the `wc_customer_lookup` table which indexes email, first_name, last_name, username

---

### Scenario 2: Guest Order Then Account Creation ⚠️

**Setup:**
- Order #1: Guest order, billing email `jane@example.com`, customer_id = `0`
- Later: Customer creates account with email `jane@example.com`, user_id = `456`
- Order #2: Logged-in order, billing email `jane@example.com`, customer_id = `456`

**Search by `jane@example.com`:**
1. ✅ **Customer search** finds user `456`
2. ✅ **Customer orders** shows Order #2 only (customer_id = 456)
3. ✅ **Guest orders** shows Order #1 only (customer_id = 0)
4. ✅ **Result:** Both orders are displayed (in separate sections)

**This works correctly!**

---

### Scenario 3: Order Reassignment 🔴 POTENTIAL GAP

**Setup:**
- Order #1: Guest order, billing email `bob@example.com`, customer_id = `0`
- Admin manually assigns order to user `789` (changes customer_id from `0` to `789`)
- User `789` has account email `bob.smith@example.com` (different from order billing email)

**Search by `bob@example.com`:**
1. ❌ **Customer search** might NOT find user `789` (email doesn't match account)
2. ❌ **Customer orders** won't show Order #1 (user not found)
3. ❌ **Guest orders** won't show Order #1 (customer_id = 789, not 0)
4. 🔴 **Result:** Order is INVISIBLE in search results!

**This is a gap!**

---

## Root Cause Analysis

The system has **two separate search paths**:

1. **Path A:** Find customers → Get their orders by customer_id
2. **Path B:** Find guest orders by billing_email (customer_id = 0 only)

**Missing Path C:** Find orders by billing_email for registered customers

When an order has:
- `billing_email` = search term
- `customer_id` > 0
- But the customer account email ≠ billing_email

The order falls through the cracks.

---

## Recommended Fix

### Option 1: Expand Guest Order Search (Simpler)

Modify `search_guest_orders_by_email()` to show ALL orders with matching billing email, not just guest orders:

```php
public function search_guest_orders_by_email( $term ) {
    // ... existing validation ...
    
    $orders = wc_get_orders([
        'billing_email' => $term,
        'limit'         => 20,
        // ... other params ...
    ]);
    
    $results = array();
    if ( ! empty( $orders ) ) {
        foreach ( $orders as $order ) {
            // Remove the customer_id === 0 filter
            $results[] = $this->format_order_for_output( $order );
        }
    }
    
    return $results;
}
```

**Pros:**
- Simple one-line change
- Catches all orders with matching billing email

**Cons:**
- May show duplicate orders (if customer was already found)
- Section labeled "Guest Orders" would be misleading

---

### Option 2: Add Billing Email Search to Customer Lookup (Better)

Modify `search_user_ids_via_customer_lookup()` to also search by billing email in the customer lookup table.

**Already implemented!** Line 260 in `includes/class-kiss-woo-search.php`:
```php
WHERE user_id > 0
AND (email LIKE %s OR first_name LIKE %s OR last_name LIKE %s OR username LIKE %s)
```

The `email` column in `wc_customer_lookup` should contain the billing email.

---

### Option 3: Deduplicate Results (Best)

Keep both searches but deduplicate in the AJAX handler:

```php
// In handle_ajax_search()
$customers    = $search->search_customers( $term );
$guest_orders = $search->search_guest_orders_by_email( $term );

// Get all order IDs already shown in customer results
$shown_order_ids = array();
foreach ( $customers as $customer ) {
    foreach ( $customer['orders_list'] as $order ) {
        $shown_order_ids[] = $order['id'];
    }
}

// Filter guest orders to exclude already-shown orders
$guest_orders = array_filter( $guest_orders, function( $order ) use ( $shown_order_ids ) {
    return ! in_array( $order['id'], $shown_order_ids, true );
});
```

---

## Testing Recommendations

1. Create test order with billing email different from customer account email
2. Search by billing email and verify order appears
3. Manually reassign a guest order to a customer and verify it still appears
4. Test with customer who has multiple orders with different billing emails

---

## Status

**Priority:** P2 (Medium)  
**Severity:** Medium  
**Impact:** Edge case - affects orders where billing email ≠ account email  
**Recommendation:** Implement Option 3 (deduplication) for comprehensive coverage

