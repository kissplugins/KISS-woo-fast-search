# Cache Busting Instructions

**Version:** 1.1.2.1  
**Issue:** Browser may be loading old JavaScript that doesn't have redirect debugging

---

## What We Did

1. **Bumped version** from `1.1.2` to `1.1.2.1`
2. **Added version logging** to JavaScript console
3. **Force browser cache refresh**

---

## How to Verify JavaScript is Updated

### Step 1: Clear Browser Cache

**Option A: Hard Refresh (Recommended)**
- **Windows/Linux:** `Ctrl + Shift + R` or `Ctrl + F5`
- **Mac:** `Cmd + Shift + R`

**Option B: Clear Cache Manually**
1. Open DevTools (F12)
2. Right-click the refresh button
3. Select "Empty Cache and Hard Reload"

**Option C: Disable Cache in DevTools**
1. Open DevTools (F12)
2. Go to Network tab
3. Check "Disable cache"
4. Keep DevTools open while testing

### Step 2: Check Console for Version

1. Open browser DevTools (F12)
2. Go to **Console** tab
3. Refresh the page
4. Look for this message:
   ```
   🔍 KISS Search JS loaded - Version 1.1.2-debug
   ```

**If you see this message:** ✅ JavaScript is updated!  
**If you don't see this message:** ❌ Still loading old cached version

---

## If Cache Won't Clear

### Method 1: Incognito/Private Window
1. Open an incognito/private browser window
2. Log into WordPress admin
3. Test the search functionality
4. This bypasses all cache

### Method 2: Different Browser
- Try Chrome if you were using Firefox
- Try Firefox if you were using Chrome
- Try Safari if you were using either

### Method 3: Check File Timestamp
1. Go to: `wp-content/plugins/KISS-woo-fast-search/admin/kiss-woo-admin.js`
2. Check the file modification time
3. Should be very recent (today)
4. If not, the file didn't update on the server

### Method 4: Force Version in URL
Add `?ver=` parameter to force reload:
```
wp-admin/admin.php?page=kiss-woo-customer-order-search&ver=1.1.2.1
```

---

## What to Look For After Cache Clear

### In Console (F12 → Console tab):

**On Page Load:**
```
🔍 KISS Search JS loaded - Version 1.1.2-debug
```

**When Searching for an Order:**
```
🔄 KISS: Redirecting to order...
{
  redirect_url: "https://...",
  should_redirect: true,
  orders: [...]
}
```

### On Search Results Page:

**You should see:**
- Yellow warning box with "🔍 DEBUG MODE: Redirect Intercepted"
- The exact redirect URL displayed
- Two test buttons
- Full response data

**You should NOT see:**
- Automatic redirect happening
- Just a list of orders without the debug box

---

## Testing Checklist

- [ ] Hard refresh the page (Ctrl+Shift+R / Cmd+Shift+R)
- [ ] Open DevTools Console (F12)
- [ ] Verify version message appears: `🔍 KISS Search JS loaded - Version 1.1.2-debug`
- [ ] Search for an order number
- [ ] Verify debug box appears (yellow warning)
- [ ] Verify redirect URL is shown
- [ ] Click "Open in New Tab" button
- [ ] Verify it goes to correct order page

---

## Common Issues

### Issue: No version message in console
**Cause:** Old JavaScript still cached  
**Solution:** Try incognito mode or different browser

### Issue: Version message shows but no debug box
**Cause:** Search isn't finding an order  
**Solution:** Check the Full Response Data to see what was returned

### Issue: Debug box shows but URL is wrong
**Cause:** Bug in URL formatter  
**Solution:** Compare with self-test page URL

### Issue: Everything works in incognito but not regular browser
**Cause:** Persistent cache or browser extension  
**Solution:** Clear all WordPress/site data for this domain

---

## Server-Side Cache

If you're using a caching plugin (WP Rocket, W3 Total Cache, etc.):

1. **Clear plugin cache:**
   - Go to the caching plugin settings
   - Click "Clear Cache" or "Purge All"

2. **Clear object cache:**
   - If using Redis/Memcached
   - Flush the object cache

3. **Disable caching temporarily:**
   - Turn off caching plugin
   - Test the functionality
   - Re-enable after testing

---

## Next Steps

Once you've verified the JavaScript is updated:

1. **Search for an order**
2. **Check the debug box**
3. **Report back:**
   - Does the debug box appear? (Yes/No)
   - What URL is shown?
   - Does clicking "Open in New Tab" work? (Yes/No)

This will tell us if it was a cache issue or something else!

