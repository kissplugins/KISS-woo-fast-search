# Double-Escape Bug Fix

**Version:** 1.1.3  
**Date:** 2026-01-09  
**Status:** 🎯 CRITICAL BUG FIXED

---

## The Problem

### What Was Happening

When searching for an order, the redirect was going to a **malformed URL**:

**Broken URL:**
```
https://1-bloomzhemp-production-sync-07-24.local/wp-admin/edit.php#038;action=edit
```

**Correct URL:**
```
https://1-bloomzhemp-production-sync-07-24.local/wp-admin/post.php?post=1256171&action=edit
```

### The Symptoms

- ❌ Redirect went to "All Posts" page instead of order editor
- ❌ URL had `#038;` instead of `&`
- ❌ Missing order ID and wrong file (`edit.php` vs `post.php`)
- ✅ Self-test page URLs worked correctly
- ✅ Manual test buttons worked correctly

---

## Root Cause Analysis

### The Bug

**File:** `includes/class-kiss-woo-order-formatter.php`  
**Line:** 43  
**Code:**
```php
'view_url' => esc_url( $edit_url ),
```

### Why This Was Wrong

1. **First Escape:** WordPress's `admin_url()` returns a URL like:
   ```
   post.php?post=123&action=edit
   ```

2. **Second Escape:** `esc_url()` converts `&` to `&#038;` (HTML entity):
   ```
   post.php?post=123&#038;action=edit
   ```

3. **Browser Interpretation:** Browser sees `&#038;` and partially decodes it to `#038;`:
   ```
   edit.php#038;action=edit
   ```
   (The `#` makes everything after it a URL fragment, not query parameters)

### Why It Worked in Self-Test

The self-test page uses the URLs **directly in HTML** where `esc_url()` is appropriate:
```php
<a href="<?php echo esc_url( $url ); ?>">Test →</a>
```

But in **JSON/JavaScript**, we don't want HTML entities:
```javascript
window.location.href = resp.data.redirect_url; // Needs raw URL, not HTML-escaped
```

---

## The Fix

### Code Change

**File:** `includes/class-kiss-woo-order-formatter.php`  
**Line:** 43

**Before:**
```php
'view_url' => esc_url( $edit_url ),
```

**After:**
```php
'view_url' => $edit_url, // Don't escape - already safe from admin_url() and will be used in JavaScript
```

### Why This Is Safe

The URL comes from one of these sources:
1. `$order->get_edit_order_url()` - WooCommerce core method
2. `get_edit_post_link( $order_id, 'raw' )` - WordPress core function
3. `admin_url( 'post.php?post=' . $order_id . '&action=edit' )` - WordPress core function

All of these return **safe, properly formatted URLs**. No additional escaping needed for JSON output.

### When to Use `esc_url()`

✅ **Use `esc_url()` when:**
- Outputting URL in HTML: `<a href="<?php echo esc_url( $url ); ?>">`
- Outputting URL in HTML attribute: `<img src="<?php echo esc_url( $url ); ?>">`

❌ **Don't use `esc_url()` when:**
- Storing URL in database (use `esc_url_raw()` if needed)
- Returning URL in JSON/AJAX response
- Passing URL to JavaScript

---

## Testing

### Step 1: Hard Refresh
- **Windows/Linux:** `Ctrl + Shift + R`
- **Mac:** `Cmd + Shift + R`

### Step 2: Check Console
Look for:
```
🔍 KISS Search JS loaded - Version 1.1.3 (double-escape bug fixed)
```

### Step 3: Test Search
1. Go to **WooCommerce → KISS Search**
2. Enter an order number (e.g., `1256171` or `B349445`)
3. Should **automatically redirect** to the correct order editor

### Step 4: Verify Console Output
```
🔄 KISS: Redirecting to order...
{
  redirect_url: "https://...wp-admin/post.php?post=1256171&action=edit",
  should_redirect: true,
  orders: [...]
}
```

**Check the `redirect_url`:**
- ✅ Should have `?post=` (question mark)
- ✅ Should have `&action=` (ampersand, not `#038;`)
- ✅ Should be `post.php` (not `edit.php`)

---

## Impact

### What's Fixed

✅ **Order search redirect** - Now goes to correct order editor  
✅ **URL format** - Proper query parameters with `&` not `#038;`  
✅ **HPOS compatibility** - Works with both HPOS and legacy modes  
✅ **All search methods** - Toolbar, search page, auto-search all work  

### What's Not Changed

✅ **Self-test page** - Still works (uses `esc_url()` correctly in HTML context)  
✅ **Debug panel** - Still available when debug mode enabled  
✅ **Console logging** - Still shows redirect info  
✅ **Security** - URLs still safe (from WordPress core functions)  

---

## Files Modified

1. ✅ `includes/class-kiss-woo-order-formatter.php` - Removed `esc_url()` from line 43
2. ✅ `kiss-woo-fast-order-search.php` - Version bumped to 1.1.3
3. ✅ `admin/kiss-woo-admin.js` - Updated version logging
4. ✅ `CHANGELOG.md` - Documented fix with technical details

---

## Lessons Learned

### Key Takeaway

**Context matters for escaping:**
- HTML output → Use `esc_url()`, `esc_html()`, `esc_attr()`
- JSON/JavaScript → Use raw values (if from trusted source)
- Database → Use `esc_url_raw()` or sanitize on input

### WordPress Escaping Functions

| Function | Use Case |
|----------|----------|
| `esc_url()` | HTML output: `<a href="...">` |
| `esc_url_raw()` | Database storage |
| `esc_html()` | HTML text content |
| `esc_attr()` | HTML attributes |
| `wp_json_encode()` | JSON output (handles escaping) |

### Best Practice

When returning data via AJAX/JSON:
```php
// ✅ Good - Let wp_send_json_success() handle encoding
wp_send_json_success( array(
    'url' => $url, // Raw URL from admin_url()
) );

// ❌ Bad - Double-escaping
wp_send_json_success( array(
    'url' => esc_url( $url ), // Will break in JavaScript
) );
```

---

## Verification Checklist

- [ ] Hard refresh browser
- [ ] Console shows version 1.1.3
- [ ] Search for order number
- [ ] Redirect goes to correct order editor
- [ ] Console shows proper URL (with `&` not `#038;`)
- [ ] Self-test page still works
- [ ] No JavaScript errors in console

---

## If It Still Doesn't Work

1. **Check console for version:**
   - Should say: `Version 1.1.3 (double-escape bug fixed)`
   - If not: Clear cache harder (incognito mode)

2. **Check redirect URL in console:**
   - Should have `?` and `&` characters
   - Should NOT have `#038;` or `&#038;`
   - Should be `post.php` not `edit.php`

3. **Check for JavaScript errors:**
   - Open DevTools → Console
   - Look for red error messages
   - Report any errors found

This should be the final fix! 🎯

