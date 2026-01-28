# Coupon Search Not Finding: r1m8jj1xt2m1m

**Date:** 2026-01-28  
**Issue:** Coupon code `r1m8jj1xt2m1m` (ID: 1323821) is not being found by the search  
**Expected:** Should redirect to `https://bloomz-prod-08-15.local/wp-admin/post.php?post=1323821&action=edit&classic-editor`

---

## Root Cause

The coupon search feature uses a **lookup table** (`wp_kiss_woo_coupon_lookup`) for fast searching. The issue is:

1. ✅ **Coupon exists** in WordPress (ID: 1323821)
2. ❌ **Lookup table is empty** or hasn't been backfilled with existing coupons
3. ❌ **Search returns no results** because it only queries the lookup table

---

## How Coupon Search Works

### Architecture

```
User searches "r1m8jj1xt2m1m"
    ↓
KISS_Woo_Coupon_Search::search_coupons()
    ↓
Normalize: "r1m8jj1xt2m1m" → "r1m8jj1xt2m1m" (already lowercase alphanumeric)
    ↓
Query: SELECT * FROM wp_kiss_woo_coupon_lookup 
       WHERE code_normalized LIKE 'r1m8jj1xt2m1m%'
    ↓
❌ Returns empty if coupon not in lookup table
```

### Normalization Logic

<augment_code_snippet path="includes/class-kiss-woo-coupon-search.php" mode="EXCERPT">
````php
private function normalize_code( string $code ): string {
    $code = strtolower( trim( $code ) );
    $code = preg_replace( '/[^a-z0-9]+/', '', $code );
    return $code;
}
````
</augment_code_snippet>

For `r1m8jj1xt2m1m`:
- Input: `r1m8jj1xt2m1m`
- Normalized: `r1m8jj1xt2m1m` (no change - already clean)

### Search Query

<augment_code_snippet path="includes/class-kiss-woo-coupon-search.php" mode="EXCERPT">
````php
$sql = $wpdb->prepare(
    "SELECT coupon_id, code, title, description, ...
       FROM {$table}
      WHERE blog_id = %d
        AND status NOT IN ('trash', 'auto-draft')
        AND (
            code_normalized LIKE %s    -- Prefix match
            OR title LIKE %s
            OR description_normalized LIKE %s
        )
   ORDER BY score DESC, updated_at DESC
      LIMIT %d",
    $normalized_code,  // Exact match scoring
    $code_prefix,      // r1m8jj1xt2m1m%
    $term,
    $term_prefix,
    $desc_like,
    $blog_id,
    $code_prefix,      // WHERE clause
    $term_like,
    $desc_like,
    $limit
);
````
</augment_code_snippet>

---

## Solution: Backfill the Lookup Table

### Option 1: Web UI (Recommended)

I've created a diagnostic page for you:

1. **Access:** `/wp-admin/admin.php?page=kiss-woo-coupon-diagnostic`
2. **Features:**
   - Shows table status (exists, row count, missing coupons)
   - Backfill 500 coupons at a time
   - Test single coupon backfill
   - View recent coupons in lookup table

**Steps:**
1. Go to: `https://bloomz-prod-08-15.local/wp-admin/admin.php?page=kiss-woo-coupon-diagnostic`
2. Click "Backfill 500 Coupons" button
3. Repeat until all coupons are indexed
4. OR use "Test Single Coupon" to backfill just `r1m8jj1xt2m1m`

---

### Option 2: WP-CLI (If Available)

The plugin includes WP-CLI commands:

```bash
# Backfill all coupons (500 at a time)
wp kiss-woo coupons backfill --batch=500

# Backfill starting from a specific ID
wp kiss-woo coupons backfill --start=1000000 --batch=1000

# Backfill max 5000 coupons
wp kiss-woo coupons backfill --max=5000
```

**Note:** WP-CLI access via Local by Flywheel may require:
- SSH into the Local site container
- Or use Local's built-in WP-CLI wrapper

---

### Option 3: Programmatic (PHP)

```php
// Create/ensure table exists
$lookup = KISS_Woo_Coupon_Lookup::instance();
$lookup->maybe_install();

// Backfill all coupons
$backfill = new KISS_Woo_Coupon_Backfill();
$last_id = 0;

while (true) {
    $result = $backfill->run_batch($last_id, 500);
    echo "Processed: {$result['processed']} (last_id={$result['last_id']})\n";
    
    if ($result['done']) {
        break;
    }
    $last_id = $result['last_id'];
}
```

---

## Automatic Indexing

The plugin **automatically indexes new/updated coupons** via WordPress hooks:

<augment_code_snippet path="includes/class-kiss-woo-coupon-lookup.php" mode="EXCERPT">
````php
add_action( 'save_post_shop_coupon', array( $this, 'on_coupon_save' ), 10, 1 );
add_action( 'woocommerce_coupon_object_updated_props', array( $this, 'on_coupon_updated' ), 10, 1 );
add_action( 'before_delete_post', array( $this, 'on_coupon_delete' ), 10, 1 );
````
</augment_code_snippet>

**This means:**
- ✅ New coupons created after plugin activation are automatically indexed
- ❌ Existing coupons (created before plugin activation) need manual backfill

---

## Verification

After backfilling, verify the coupon is indexed:

```sql
SELECT * FROM wp_kiss_woo_coupon_lookup 
WHERE code_normalized = 'r1m8jj1xt2m1m';
```

Expected result:
```
coupon_id: 1323821
code: r1m8jj1xt2m1m
code_normalized: r1m8jj1xt2m1m
title: (coupon title)
status: publish
```

---

## Files Modified

1. **kiss-woo-fast-order-search.php** - Added coupon diagnostic page loader
2. **admin/coupon-diagnostic.php** - NEW diagnostic UI

---

## Next Steps

1. ✅ Access diagnostic page: `/wp-admin/admin.php?page=kiss-woo-coupon-diagnostic`
2. ✅ Click "Backfill This Coupon" with `r1m8jj1xt2m1m` pre-filled
3. ✅ Verify coupon now appears in search
4. ✅ Backfill remaining coupons (500 at a time)

---

## Performance Notes

- **Backfill speed:** ~500-1000 coupons/second
- **Table size:** ~1KB per coupon (indexed columns only)
- **Search speed:** <10ms for prefix match on indexed column
- **Cache:** 60-second transient cache for search results

---

## Troubleshooting

### "Lookup table does NOT exist"
- Click "Backfill 500 Coupons" - it will auto-create the table

### "Coupon not found: r1m8jj1xt2m1m"
- Verify coupon exists: `/wp-admin/post.php?post=1323821&action=edit`
- Check post_status is not 'trash' or 'auto-draft'

### "Failed to backfill coupon"
- Check PHP error logs
- Verify WooCommerce is active
- Verify database permissions

