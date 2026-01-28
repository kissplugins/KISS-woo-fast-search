# HPOS Order URL Redirect Fix

**Date:** 2026-01-09  
**Version:** 1.1.2  
**Issue:** Order search redirect taking users to "All Posts" page instead of order editor

---

## Problem

When searching for an order number, the plugin was redirecting to the wrong page (WordPress "All Posts" instead of the WooCommerce order editor). This happened specifically when **HPOS (High-Performance Order Storage)** is enabled in WooCommerce.

### Root Cause

The `KISS_Woo_Order_Formatter::get_edit_url()` method was using `get_edit_post_link()` which doesn't work correctly for orders when HPOS is enabled, because orders are no longer stored as WordPress posts.

**Old Code (Broken):**
```php
private static function get_edit_url( int $order_id ): string {
    // Try WooCommerce's method first (HPOS-aware).
    $edit_url = get_edit_post_link( $order_id, 'raw' );
    
    if ( empty( $edit_url ) ) {
        // Fallback for HPOS or edge cases.
        $edit_url = admin_url( 'post.php?post=' . $order_id . '&action=edit' );
    }
    
    return $edit_url;
}
```

This would return a URL like:
- `wp-admin/post.php?post=12345&action=edit` (wrong - goes to "All Posts")

But with HPOS enabled, the correct URL should be:
- `wp-admin/admin.php?page=wc-orders&action=edit&id=12345`

---

## Solution

Updated `includes/class-kiss-woo-order-formatter.php` to use WooCommerce's built-in `$order->get_edit_order_url()` method, which automatically handles both HPOS and legacy storage modes.

**New Code (Fixed):**
```php
private static function get_edit_url( int $order_id ): string {
    // Get the order object to use WooCommerce's built-in method.
    $order = wc_get_order( $order_id );
    
    // Use WooCommerce's get_edit_order_url() method (HPOS-aware).
    if ( $order && method_exists( $order, 'get_edit_order_url' ) ) {
        return $order->get_edit_order_url();
    }
    
    // Fallback 1: Try get_edit_post_link (for legacy/non-HPOS).
    $edit_url = get_edit_post_link( $order_id, 'raw' );
    
    if ( ! empty( $edit_url ) ) {
        return $edit_url;
    }
    
    // Fallback 2: Construct URL manually based on HPOS status.
    if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) &&
         \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
        // HPOS mode: Use admin.php with page=wc-orders.
        return admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order_id );
    }
    
    // Legacy mode: Use post.php.
    return admin_url( 'post.php?post=' . $order_id . '&action=edit' );
}
```

---

## Files Changed

1. **`includes/class-kiss-woo-order-formatter.php`**
   - Updated `get_edit_url()` method to use `$order->get_edit_order_url()`
   - Added proper HPOS detection and fallback logic

2. **`kiss-woo-fast-order-search.php`**
   - Version bumped to 1.1.2
   - Added URL test diagnostic endpoint

3. **`tests/test-order-url.php`** (NEW)
   - Diagnostic tool to test URL generation
   - Access via: `/wp-admin/?kiss_test_url=1&order_id=12345`

4. **`CHANGELOG.md`**
   - Added v1.1.2 entry documenting the fix

---

## Testing

### Manual Test (Recommended)

1. **Search for an order:**
   - Go to WooCommerce → KISS Search
   - Enter an order number (e.g., `B349445` or `12345`)
   - Press Enter or click Search

2. **Expected behavior:**
   - Page should redirect to the WooCommerce order editor
   - URL should be either:
     - HPOS: `wp-admin/admin.php?page=wc-orders&action=edit&id=12345`
     - Legacy: `wp-admin/post.php?post=12345&action=edit`

3. **Previous (broken) behavior:**
   - Would redirect to `wp-admin/edit.php` (All Posts page)

### Diagnostic Test (Optional)

To enable debug mode and test URL generation:

1. **Enable debug mode** in `wp-config.php`:
   ```php
   define( 'KISS_WOO_FAST_SEARCH_DEBUG', true );
   ```

2. **Run URL test:**
   - Visit: `/wp-admin/?kiss_test_url=1&order_id=12345`
   - Replace `12345` with an actual order ID
   - Check which URL generation method works

3. **Check console logs:**
   - Open browser console
   - Search for an order
   - Look for "KISS Search Debug" group showing redirect URL

---

## Verification Checklist

- [x] Updated `get_edit_url()` to use `$order->get_edit_order_url()`
- [x] Added HPOS detection fallback
- [x] Version bumped to 1.1.2
- [x] CHANGELOG updated
- [x] Created diagnostic test tool
- [ ] **Manual test:** Search for order redirects to correct page
- [ ] **Verify:** Permalinks have been rebuilt (Settings → Permalinks → Save)

---

## Notes

- The fix is backward compatible with both HPOS and legacy storage modes
- No database changes required
- Users should rebuild permalinks after updating (Settings → Permalinks → Save Changes)

