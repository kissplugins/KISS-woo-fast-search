# KISS Self-Test Page Guide

**Version:** 1.1.2  
**Purpose:** Diagnose order URL generation and redirect issues

---

## How to Access

1. Go to your WordPress admin
2. Navigate to **WooCommerce → KISS Self-Test**

---

## What the Self-Test Page Shows

### 1. System Status
- **WooCommerce Version**: Shows your WC version
- **HPOS Enabled**: Whether High-Performance Order Storage is active
- **Sequential Order Numbers Pro**: Whether the plugin is installed
- **Debug Mode**: Whether `KISS_WOO_FAST_SEARCH_DEBUG` is enabled

### 2. URL Generation Tests
Tests 5 different methods of generating order edit URLs:

1. **$order->get_edit_order_url()** - Recommended (HPOS-aware)
2. **get_edit_post_link()** - WordPress function
3. **admin_url( post.php )** - Legacy fallback
4. **HPOS-aware manual** - Based on HPOS detection
5. **KISS_Woo_Order_Formatter** - Currently used by the plugin (highlighted)

**How to use:**
- Click each "Test →" button
- The correct URL should take you to the order editor
- If it takes you to "All Posts", that method is broken

### 3. Live Search Test
- Tests the actual AJAX search functionality
- Shows the exact redirect URL that would be used
- Displays debug data if available

---

## Troubleshooting Steps

### If redirect is still going to "All Posts":

1. **Check which URL method works:**
   - In the "URL Generation Tests" section
   - Click each "Test →" button
   - Note which one takes you to the correct order page

2. **Check the AJAX response:**
   - Click "Run Search Test" in the Live Search Test section
   - Look at the `redirect_url` value
   - Click "Test This URL →" to verify it works

3. **Check browser console:**
   - Open browser DevTools (F12)
   - Go to Console tab
   - Search for an order
   - Look for the message: `🔄 KISS: Redirecting to order...`
   - Check the `redirect_url` value

4. **Compare URLs:**
   - If method #1 works but method #5 doesn't, there's a bug in the formatter
   - If none of the methods work, it's a WooCommerce/HPOS configuration issue

---

## Expected Results

### With HPOS Enabled:
- Method #1 should return: `admin.php?page=wc-orders&action=edit&id=12345`
- Method #4 should return: `admin.php?page=wc-orders&action=edit&id=12345`
- Method #5 should match method #1

### Without HPOS (Legacy):
- Method #1 should return: `post.php?post=12345&action=edit`
- Method #2 should return: `post.php?post=12345&action=edit`
- Method #5 should match method #1

---

## Debug Data

If debug mode is enabled (`KISS_WOO_FAST_SEARCH_DEBUG = true`), you'll see:

- **Traces**: Detailed execution log
- **Memory usage**: Peak memory consumption
- **PHP/WC versions**: System information
- **Timing data**: How long each operation took

---

## Common Issues

### Issue: All URLs go to "All Posts"
**Cause:** HPOS is enabled but WooCommerce version is too old  
**Solution:** Update WooCommerce to 7.1+

### Issue: Method #1 shows "Method not available"
**Cause:** Very old WooCommerce version  
**Solution:** Update WooCommerce

### Issue: Method #1 works but #5 doesn't
**Cause:** Bug in KISS_Woo_Order_Formatter  
**Solution:** Report this - it's a plugin bug

### Issue: Redirect URL is correct but still goes to wrong page
**Cause:** Permalink/rewrite rules issue  
**Solution:** 
1. Go to Settings → Permalinks
2. Click "Save Changes" (don't change anything)
3. Test again

---

## Reporting Issues

If the self-test reveals a problem, include this information:

1. **System Status** (from the self-test page)
2. **Which URL methods work** (from URL Generation Tests)
3. **The redirect_url value** (from Live Search Test)
4. **Browser console output** (the 🔄 KISS message)
5. **Debug traces** (if debug mode is enabled)

---

## Next Steps

After identifying the issue:

1. If method #1 works → Update the formatter to use that method
2. If no methods work → WooCommerce/HPOS configuration issue
3. If redirect URL is correct but still fails → Permalink issue

