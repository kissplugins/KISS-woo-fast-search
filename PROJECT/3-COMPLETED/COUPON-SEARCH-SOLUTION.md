# Coupon Search Solution: r1m8jj1xt2m1m Not Found

**Date:** 2026-01-28
**Status:** ✅ COMPLETE - All 17,499 Coupons Backfilled

---

## 🔍 Problem Confirmed

- ✅ Coupon exists: ID `1323821`, Code `r1m8jj1xt2m1m`
- ✅ Lookup table exists: `wp_kiss_woo_coupon_lookup`
- ❌ Lookup table was EMPTY: 0 rows
- ❌ Total coupons in WordPress: **17,499**
- ❌ WP-CLI backfill processed 0 coupons

## ✅ Root Cause Identified

**Problem:** `wc_get_coupon()` helper function not available in WP-CLI and early admin contexts

**Evidence:**
```
Debug: upsert_coupon(6734): wc_get_coupon function not found
Debug: upsert_coupon(6841): wc_get_coupon function not found
...
Processed: 0 (last_id=142994)
```

**Solution:** Changed `KISS_Woo_Coupon_Lookup::upsert_coupon()` to use `new WC_Coupon()` directly instead of the `wc_get_coupon()` helper function.

## ✅ Solution Implemented & Verified

**Code Changes:**
- Modified `includes/class-kiss-woo-coupon-lookup.php`
- Replaced `wc_get_coupon($coupon_id)` with `new WC_Coupon($coupon_id)`
- Added debug logging for WP-CLI troubleshooting
- Added try/catch for exception handling

**Backfill Results:**
```
Processed: 1000 (last_id=205075)
Processed: 2000 (last_id=303885)
...
Processed: 17000 (last_id=1287144)
Processed: 17499 (last_id=1323854)
Success: Backfill complete. Total processed: 17499
```

**Verification:**
```
✅ Coupon found in lookup table:
  ID: 1323821
  Code: r1m8jj1xt2m1m
  Code Normalized: r1m8jj1xt2m1m
  Title: r1m8jj1xt2m1m

Total coupons in lookup table: 17499
```

---

## ✅ Solution: Use Web UI Diagnostic Page

I've created a diagnostic page that you can access directly in your browser.

### **Access URL:**
```
https://bloomz-prod-08-15.local/wp-admin/admin.php?page=kiss-woo-coupon-diagnostic
```

### **What It Does:**
1. Shows table status (row count, missing coupons)
2. Backfill 500 coupons at a time with one click
3. Test single coupon backfill (pre-filled with `r1m8jj1xt2m1m`)
4. View recent coupons in lookup table

---

## 🚀 Quick Fix Steps

### **Option 1: Backfill Just This One Coupon**
1. Go to: `https://bloomz-prod-08-15.local/wp-admin/admin.php?page=kiss-woo-coupon-diagnostic`
2. Scroll to "Test Single Coupon" section
3. The field is pre-filled with `r1m8jj1xt2m1m`
4. Click **"Backfill This Coupon"** button
5. You should see: ✅ Successfully backfilled coupon: r1m8jj1xt2m1m (ID: 1323821)
6. Test the search again

### **Option 2: Backfill All 17,499 Coupons**
1. Go to the same diagnostic page
2. Click **"Backfill 500 Coupons"** button
3. Wait for it to complete (shows "Backfilled X coupons")
4. Click again to backfill the next 500
5. Repeat ~35 times to backfill all 17,499 coupons

**Note:** Each batch takes ~1-2 seconds, so total time is ~1-2 minutes for all coupons.

---

## 📋 Files Created

1. ✅ **admin/coupon-diagnostic.php** - Diagnostic UI page
2. ✅ **kiss-woo-fast-order-search.php** - Updated to load diagnostic page
3. ✅ **PROJECT/1-INBOX/COUPON-SEARCH-DIAGNOSTIC.md** - Full technical documentation

---

## 🔧 Why WP-CLI Backfill Failed

The WP-CLI command ran but processed 0 coupons:
```
Processed: 0 (last_id=142994)
Processed: 0 (last_id=205075)
...
Success: Backfill complete. Total processed: 0 (last_id=1323854)
```

**Possible reasons:**
1. The `upsert_coupon()` method is returning `false` for all coupons
2. There might be a silent error in the coupon loading or row building
3. The `wpdb->replace()` might be failing silently

**The web UI will show you the actual error messages**, which WP-CLI might be suppressing.

---

## 🎯 After Backfill

Once the coupon is backfilled, the search will work:

1. User searches: `r1m8jj1xt2m1m`
2. Normalized to: `r1m8jj1xt2m1m`
3. Query: `SELECT * FROM wp_kiss_woo_coupon_lookup WHERE code_normalized LIKE 'r1m8jj1xt2m1m%'`
4. Returns: Coupon ID 1323821
5. Redirects to: `https://bloomz-prod-08-15.local/wp-admin/post.php?post=1323821&action=edit&classic-editor`

---

## 🔄 Automatic Indexing

After the initial backfill, **new coupons are automatically indexed** via WordPress hooks:
- `save_post_shop_coupon`
- `woocommerce_coupon_object_updated_props`
- `before_delete_post`

So you only need to backfill once for existing coupons.

---

## 📊 Verification

After backfilling, you can verify on the diagnostic page:
- "Total Coupons in Lookup Table" should show 17,499 (or at least 1 if you only backfilled the single coupon)
- "Recent Coupons in Lookup Table" will show the last 10 indexed coupons
- You should see `r1m8jj1xt2m1m` in the list

---

## 🎉 Next Steps

1. **Access the diagnostic page** (URL above)
2. **Backfill the coupon** (or all coupons)
3. **Test the search** in the main plugin UI
4. **Verify it redirects** to the correct edit URL

Let me know if you encounter any errors on the diagnostic page!

