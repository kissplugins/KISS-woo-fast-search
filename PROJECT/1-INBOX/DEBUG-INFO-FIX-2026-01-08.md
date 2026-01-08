# Debug Info Fix - Order Search Trace Preservation

**Date**: 2026-01-08  
**Status**: ✅ Complete  
**Version**: Unreleased (will be in next version)

## Problem

When searching for an order number that doesn't exist (e.g., `#B331580`), the debug panel would show **customer search debug info** instead of **order search debug info**. This made it impossible to see why the order wasn't found.

### Root Cause

The AJAX handler in `kiss-woo-fast-order-search.php` had this flow:

1. Line 136: Call `search_orders_by_number()` → sets `$this->last_lookup_debug` with order trace
2. Line 139: Get debug info → captures order debug ✅
3. Line 143: If order found (count === 1), return early with order debug ✅
4. Line 165: **If order NOT found**, call `search_customers()` → **OVERWRITES** `$this->last_lookup_debug` ❌
5. Line 171: Get debug info → now returns **customer** debug instead of order debug ❌

### Example of the Problem

Searching for `#B331580` would show:

```json
{
  "is_order_like": true,
  "term": "#B331580",
  "orders_found": 0,
  "search_debug": {
    "enabled": true,
    "mode": "prefix_multi_column",
    "table": "wp_wc_customer_lookup",
    "hit": false,
    "count": 0
  }
}
```

This shows **customer lookup** debug, not the order search trace with fast path and meta lookup attempts!

## Solution

Modified `kiss-woo-fast-order-search.php` (lines 134-200) to:

1. **Preserve order debug info** before calling `search_customers()`
2. **Include both** order and customer debug in the response
3. **Separate the debug info** into `order_search_debug` and `customer_search_debug`

### Code Changes

```php
// Preserve order search debug info (will be overwritten by customer search)
$order_debug_info = null;

if ( $is_order_like ) {
    $orders = $search->search_orders_by_number( $term );
    
    // Get debug info from the order search BEFORE it gets overwritten
    $order_debug_info = $search->get_last_lookup_debug();
    
    // ... rest of logic
}

// Run customer search
$customers = $search->search_customers( $term );
$customer_debug_info = $search->get_last_lookup_debug();

// Build comprehensive debug info
$debug_info = array(
    'is_order_like'   => $is_order_like,
    'term'            => $term,
    'orders_found'    => count( $orders ),
);

// Include order search debug if we did an order search
if ( $order_debug_info ) {
    $debug_info['order_search_debug'] = $order_debug_info;
}

// Include customer search debug
$debug_info['customer_search_debug'] = $customer_debug_info;
```

## Result

Now when searching for `#B331580`, the debug panel will show:

```json
{
  "is_order_like": true,
  "term": "#B331580",
  "orders_found": 0,
  "order_search_debug": {
    "function": "search_orders_by_number",
    "term": "#B331580",
    "parsed": { "id": 331580, "expected_number": "B331580" },
    "fast_path": {
      "tried": true,
      "order_id": 331580,
      "order_exists": false
    },
    "meta_lookup": { ... },
    "result": "not_found",
    "elapsed_ms": 45.23,
    "trace": [
      "Starting search_orders_by_number",
      "Parsed term: {...}",
      "Attempting fast path: wc_get_order(331580)",
      "Fast path order exists: NO",
      "Calling search_order_by_meta_number",
      "NO MATCH FOUND - Returning empty array"
    ]
  },
  "customer_search_debug": {
    "enabled": true,
    "mode": "prefix_multi_column",
    "table": "wp_wc_customer_lookup",
    "hit": false,
    "count": 0
  }
}
```

## Benefits

1. ✅ **Full visibility** into why an order wasn't found
2. ✅ **See both** fast path and meta lookup attempts
3. ✅ **Trace array** shows step-by-step execution
4. ✅ **Customer search debug** still available for debugging customer lookups
5. ✅ **No breaking changes** - just adds more debug info

## Testing

To test:
1. Search for a non-existent order number (e.g., `#B999999`)
2. Check the debug panel in the search results
3. Verify you see `order_search_debug` with the trace array
4. Verify you see `customer_search_debug` as well

## Files Modified

- `kiss-woo-fast-order-search.php` (lines 134-200)
- `CHANGELOG.md` (added to Unreleased section)

