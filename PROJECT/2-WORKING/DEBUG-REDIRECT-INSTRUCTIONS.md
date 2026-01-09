# Debug Redirect Instructions

**Version:** 1.1.2  
**Purpose:** See the exact redirect URL being generated instead of auto-redirecting

---

## What Changed

The search functionality now **shows the redirect URL on-screen** instead of automatically redirecting. This lets you see exactly what URL is being generated and test it manually.

---

## How to Test

### Step 1: Search for an Order

1. Go to **WooCommerce → KISS Search**
2. Enter an order number (e.g., `B349445` or `1256171`)
3. Press Enter or click Search

### Step 2: Review the Debug Output

Instead of redirecting, you'll now see a **yellow warning box** with:

```
🔍 DEBUG MODE: Redirect Intercepted

The plugin wants to redirect you to:
[URL will be shown here]

Test this URL:
[Open in New Tab →] [Navigate to URL →]

Full Response Data
[Expandable JSON data]
```

### Step 3: Compare URLs

**From Self-Test Page (Working):**
```
https://1-bloomzhemp-production-sync-07-24.local/wp-admin/post.php?post=1256171&action=edit
```

**From Search Results (Check this):**
```
[This is what we need to see]
```

---

## What to Look For

### ✅ If URLs Match
- Both URLs should be identical
- If they match but redirect still fails, it's a different issue (permalink/rewrite rules)

### ❌ If URLs Don't Match
- The search is generating a different URL than the self-test
- This indicates a bug in the `KISS_Woo_Order_Formatter::get_edit_url()` method
- We need to see what the difference is

### 🔍 Common URL Patterns

**HPOS Enabled:**
```
wp-admin/admin.php?page=wc-orders&action=edit&id=1256171
```

**Legacy Mode (Your Case):**
```
wp-admin/post.php?post=1256171&action=edit
```

**Wrong (Goes to All Posts):**
```
wp-admin/edit.php
```
or
```
wp-admin/post.php (without parameters)
```

---

## Testing the URL

### Option 1: Open in New Tab
- Click **"Open in New Tab →"** button
- This opens the URL in a new tab
- Check if it goes to the correct order editor

### Option 2: Navigate Directly
- Click **"Navigate to URL →"** button
- This navigates in the current tab
- Same as if auto-redirect was working

### Option 3: Copy & Inspect
- Right-click the URL text
- Copy it
- Compare with the working URL from self-test
- Look for differences

---

## Expected Results

### Scenario 1: URLs Match & Work
**Diagnosis:** The formatter is generating the correct URL  
**Next Step:** Check why the redirect itself is failing (JavaScript issue, browser blocking, etc.)

### Scenario 2: URLs Match & Don't Work
**Diagnosis:** The URL format itself is wrong for your setup  
**Next Step:** Check WooCommerce/HPOS configuration, rebuild permalinks

### Scenario 3: URLs Don't Match
**Diagnosis:** Bug in the formatter - it's generating different URLs in different contexts  
**Next Step:** Fix the `get_edit_url()` method to be consistent

### Scenario 4: URL is Incomplete/Malformed
**Diagnosis:** Data is missing (order ID, etc.)  
**Next Step:** Check the "Full Response Data" to see what order data was returned

---

## Full Response Data

Click the **"Full Response Data"** dropdown to see:

```json
{
  "customers": [],
  "guest_orders": [],
  "orders": [
    {
      "id": 1256171,
      "order_number": "B349445",
      "view_url": "https://...",  // ← This is the redirect URL
      ...
    }
  ],
  "should_redirect_to_order": true,
  "redirect_url": "https://...",  // ← This is what's used for redirect
  "search_time": 0.05,
  "debug": { ... }
}
```

**Key Fields:**
- `orders[0].view_url` - URL from the formatter
- `redirect_url` - URL actually used for redirect (should match `view_url`)
- `should_redirect_to_order` - Should be `true` for order searches

---

## Re-enabling Auto-Redirect

Once we've identified the issue, to restore auto-redirect:

1. Open `admin/kiss-woo-admin.js`
2. Find line ~215: `// UNCOMMENT THIS LINE TO RE-ENABLE AUTO-REDIRECT:`
3. Uncomment the next line: `window.location.href = resp.data.redirect_url;`
4. Comment out or remove the debug HTML code above it

Or just let me know and I'll do it!

---

## What to Report Back

Please share:

1. **The redirect URL shown** (from the yellow box)
2. **Does it match the self-test URL?** (Yes/No)
3. **What happens when you click "Open in New Tab"?** (Correct page / Wrong page / Error)
4. **Screenshot of the debug output** (if possible)

This will tell us exactly what's wrong!

