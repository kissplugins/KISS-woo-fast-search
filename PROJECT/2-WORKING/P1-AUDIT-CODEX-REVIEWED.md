# P1 Audit Codex - Security & Performance Review

## Status: REVIEWED - 2026-01-28

---

## Finding #1: Unconditional error_log() with user IDs and memory metrics

**Location:** `class-kiss-woo-search.php` (lines 1022, 1060, 1094)

**Severity:** ⚠️ **MEDIUM** (Security/Privacy)

**Issue:**
```php
error_log( '[KISS_WOO_COS] get_recent_orders_for_customers START - user_ids: ' . implode( ',', $user_ids ) . ' | memory: ' . round( memory_get_usage() / 1024 / 1024, 2 ) . 'MB' );
```

**Analysis:**
- ⚠️ **PARTIALLY AGREE** - Violates "respect WP_DEBUG settings"
- User IDs in isolation are NOT PII - only useful to trusted parties with DB access
- However, unconditional logging in production is still poor practice
- Memory metrics are less sensitive but still expose system internals
- **You're right:** This is rated Medium because User IDs alone aren't directly identifiable

**Proposed Fix:**
Wrap all debug logging in `WP_DEBUG` check and use the existing debug tracer:

```php
// ❌ BEFORE (unconditional)
error_log( '[KISS_WOO_COS] get_recent_orders_for_customers START - user_ids: ' . implode( ',', $user_ids ) . ' | memory: ' . round( memory_get_usage() / 1024 / 1024, 2 ) . 'MB' );

// ✅ AFTER (conditional + use tracer)
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    KISS_Woo_Debug_Tracer::log( 'Search', 'get_recent_orders_start', array(
        'user_count' => count( $user_ids ), // Don't log actual IDs
        'memory_mb'  => round( memory_get_usage() / 1024 / 1024, 2 ),
    ) );
}
```

**Impact:** Low - Only affects debugging, no functional changes

**Priority:** Medium - Should fix before production release

**Effort:** 🟢 **LOW** (1-2 hours)
- Replace 3 error_log() calls with debug tracer
- Add WP_DEBUG conditional checks
- Test that debug output still works when WP_DEBUG=true

**Risk of Breaking Change:** 🟢 **VERY LOW**
- Only affects debug logging, not core functionality
- Existing debug tracer is already tested and working
- No user-facing changes

---

## Finding #2: Non-sargable LIKE scans on coupon lookup

**Location:** `class-kiss-woo-coupon-search.php` (line 65-99)

**Severity:** 🔴 **HIGH** (Performance - CRITICAL)

**Issue:**
```php
// Lines 65-68: Variable definitions
$term_like = '%' . $wpdb->esc_like( $term ) . '%';   // INFIX
$term_prefix = $wpdb->esc_like( $term ) . '%';       // PREFIX (UNUSED!)
$code_prefix = $wpdb->esc_like( $normalized_code ) . '%';  // PREFIX
$desc_like = '%' . $wpdb->esc_like( $normalized_text ) . '%';  // INFIX

// Lines 84-88, 97-99: WHERE clause
WHERE blog_id = %d
  AND status NOT IN ('trash', 'auto-draft')
  AND (
      code_normalized LIKE %s       -- Uses $code_prefix (term%) ✅
      OR title LIKE %s              -- Uses $term_like (%term%) ❌ WRONG!
      OR description_normalized LIKE %s  -- Uses $desc_like (%term%) ❌
  )
```

**CRITICAL ERROR IN MY PREVIOUS ANALYSIS:**
- ❌ **I WAS WRONG** - Title search uses `$term_like` (infix), NOT `$term_prefix`
- ❌ **2 out of 3 conditions use infix search** (`%term%`)
- ❌ **MySQL abandons ALL indexes when OR contains infix searches**
- 🔴 **This will scan ALL 360k rows on EVERY search**

**Analysis:**
- ✅ **STRONGLY AGREE** - This is a **critical performance bug**, not a UX tradeoff
- Even though `code_normalized` could use an index, the `OR title LIKE '%term%'` defeats it
- With OR conditions, MySQL uses the least restrictive path (full table scan)
- At 360k coupons, this will cause:
  - Multi-second query times
  - Database locks
  - Potential timeouts
  - Poor user experience

**Proposed Fix (REQUIRED):**

**Option 1: Use prefix search only (RECOMMENDED - simplest fix)**
```php
// Line 98: Change from $term_like to $term_prefix
WHERE blog_id = %d
  AND status NOT IN ('trash', 'auto-draft')
  AND (
      code_normalized LIKE %s       -- $code_prefix (term%) ✅
      OR title LIKE %s              -- $term_prefix (term%) ✅ FIXED!
      OR description_normalized LIKE %s  -- $desc_prefix (term%) ✅ FIXED!
  )
```

**Option 2: Separate queries with UNION (better UX, more complex)**
```php
// Query 1: Fast prefix search (uses indexes)
SELECT ... WHERE code_normalized LIKE 'term%' OR title LIKE 'term%'
UNION
// Query 2: Slower infix search on description only (limited scope)
SELECT ... WHERE description_normalized LIKE '%term%' LIMIT 5
```

**Option 3: Add FULLTEXT index (best performance, requires MySQL 5.6+)**
```sql
ALTER TABLE wp_kiss_woo_coupon_lookup
ADD FULLTEXT INDEX idx_search_fulltext (code_normalized, title, description_normalized);
```
```php
WHERE MATCH(code_normalized, title, description_normalized) AGAINST(%s IN BOOLEAN MODE)
```

**Impact:**
- **Option 1:** High UX impact - can't find "SUMMER" in "BIGSUMMER2024"
- **Option 2:** Low UX impact - still finds infix matches in description
- **Option 3:** No UX impact - maintains current behavior with better performance

**Priority:** 🔴 **CRITICAL** - Must fix before deploying to sites with 100k+ coupons

**Recommendation:** ✅ **MUST FIX** - Use Option 1 (prefix only) or Option 3 (FULLTEXT)

**Effort:**
- **Option 1 (Prefix only):** � **LOW** (1-2 hours) - Change 2 variables, test
- **Option 2 (UNION):** 🟡 **MEDIUM** (4-6 hours) - Rewrite query, test performance
- **Option 3 (FULLTEXT):** 🟡 **MEDIUM** (3-5 hours) - Add index, rewrite query, test

**Risk of Breaking Change:**
- **Option 1:** 🟡 **MEDIUM** - High UX impact (can't find infix matches)
- **Option 2:** 🟢 **LOW** - Maintains UX, just changes query structure
- **Option 3:** 🟢 **LOW** - No UX impact, requires MySQL 5.6+ (99% of sites have this)

---

## Finding #3: Fallback search does LIKE %term% + N+1 WC_Coupon loads

**Location:** `class-kiss-woo-coupon-search.php` (lines 145-179)

**Severity:** 🔴 **HIGH** (Performance)

**Issue:**
```php
// Query wp_posts with LIKE %term%
$sql = $wpdb->prepare(
    "SELECT ID FROM {$wpdb->posts}
     WHERE post_type = 'shop_coupon'
       AND post_title LIKE %s
     LIMIT %d",
    $term_like,  // %term% - full table scan!
    $limit
);

$coupon_ids = $wpdb->get_col( $sql );

// N+1 problem: Load each coupon individually
foreach ( $coupon_ids as $coupon_id ) {
    $coupon = new WC_Coupon( $coupon_id );  // Separate query per coupon!
    $results[] = KISS_Woo_Coupon_Formatter::format_from_coupon( $coupon );
}
```

**Analysis:**
- ✅ **STRONGLY AGREE** - This is a performance hotspot
- ❌ **BUT** - Fallback is **intentionally temporary**:
  - Only runs when lookup table is empty/out-of-date
  - Lazy backfill indexes up to 10 coupons per search
  - Lookup table fills gradually, reducing fallback usage
- ❌ Still violates "minimize DB calls / avoid queries in loops"




**Proposed Fix:**
Batch load coupon meta instead of N+1 queries:

```php
// ✅ AFTER (batch load)
$coupon_ids = $wpdb->get_col( $sql );

if ( empty( $coupon_ids ) ) {
    return array();
}

// Batch load all coupon meta in one query
$placeholders = implode( ',', array_fill( 0, count( $coupon_ids ), '%d' ) );
$meta_keys = array( 'discount_type', 'coupon_amount', 'date_expires', 'usage_limit', 'usage_limit_per_user', 'usage_count', 'free_shipping' );
$meta_key_placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );

$meta_sql = $wpdb->prepare(
    "SELECT post_id, meta_key, meta_value
       FROM {$wpdb->postmeta}
      WHERE post_id IN ($placeholders)
        AND meta_key IN ($meta_key_placeholders)",
    array_merge( $coupon_ids, $meta_keys )
);

$meta_rows = $wpdb->get_results( $meta_sql );

// Group meta by coupon ID
$meta_by_coupon = array();
foreach ( $meta_rows as $row ) {
    $meta_by_coupon[ $row->post_id ][ $row->meta_key ] = $row->meta_value;
}

// Format results without loading WC_Coupon objects
$results = array();
foreach ( $coupon_ids as $coupon_id ) {
    $meta = $meta_by_coupon[ $coupon_id ] ?? array();
    $results[] = KISS_Woo_Coupon_Formatter::format_from_meta( $coupon_id, $meta );
}
```

**Impact:** High - Reduces fallback search from N+1 queries to 2 queries

**Priority:** High - Should fix before production release

**Alternative:** Accept current implementation since:
1. Fallback is temporary (lazy backfill fills lookup table)
2. Limited to 20 coupons max per search
3. Only runs when lookup table is empty

**Effort:** 🟡 **MEDIUM** (4-6 hours)
- Rewrite fallback_search() to batch load meta
- Add new format_from_meta() method to formatter
- Test with empty lookup table to verify fallback still works
- Test lazy backfill still triggers correctly

**Risk of Breaking Change:** 🟡 **MEDIUM**
- Fallback is critical path when lookup table is empty
- New format_from_meta() must handle all edge cases (missing meta, invalid values)
- Could break coupon search on fresh installs or after table rebuild
- **Mitigation:** Extensive testing + keep WC_Coupon fallback as last resort

---

## Summary & Recommendations

| Finding | Severity | Agree? | Fix Priority | Effort | Risk | Action |
|---------|----------|--------|--------------|--------|------|--------|
| #1 - Unconditional error_log | Medium | ⚠️ Partial | Medium | 🟢 Low (1-2h) | 🟢 Very Low | Wrap in WP_DEBUG check |
| #2 - Infix LIKE scans (2/3 conditions) | 🔴 **HIGH** | ✅ **YES** | 🔴 **CRITICAL** | 🟢 Low (1-2h) Option 1<br>🟡 Med (3-5h) Option 3 | 🟡 Medium (UX)<br>🟢 Low (FULLTEXT) | **MUST FIX** - Use prefix or FULLTEXT |
| #3 - Fallback N+1 queries | High | ✅ Yes | High | 🟡 Medium (4-6h) | 🟡 Medium (critical path) | Batch load meta OR accept as temporary |

---

## Recommended Action Plan

### Phase 1: Critical Fixes (BEFORE PRODUCTION - BLOCKING)
1. 🔴 **FIX #2 IMMEDIATELY** - This will break sites with 100k+ coupons:
   - **Recommended:** Option 3 (FULLTEXT index) - Best performance, no UX impact
   - **Alternative:** Option 1 (Prefix only) - Quick fix, but degrades UX
   - **DO NOT DEPLOY** without fixing this
2. ✅ **Fix #1**: Wrap all error_log() in WP_DEBUG checks (easy win)
3. ⚠️ **Fix #3**: Either:
   - Option A: Batch load coupon meta (recommended)
   - Option B: Accept current implementation as temporary (lazy backfill mitigates)

### Phase 2: Monitor & Optimize (Post-Launch)
4. 📊 **Monitor #3**: Track fallback usage (should decrease over time as lookup table fills)

---

## Notes

- **User IDs are NOT PII in isolation** - Only useful to trusted parties with DB access
- **Finding #1** is rated Medium because it's poor practice, not a security risk
- **Finding #2** is a **CRITICAL BUG**, not a UX tradeoff - I was wrong in my initial analysis
- **Finding #3** is mitigated by lazy backfill strategy
- All findings are in `feature/add-coupon-search` branch, not yet in `main`

---

## ⚠️ CRITICAL UPDATE - Finding #2

**I made a critical error in my initial analysis.** External review correctly identified that:

1. ❌ **Title search uses `$term_like` (infix), NOT `$term_prefix`**
2. ❌ **2 out of 3 OR conditions use infix search (`%term%`)**
3. ❌ **MySQL abandons ALL indexes when OR contains infix searches**
4. 🔴 **This will scan 360k rows on EVERY search**

**This is NOT a UX tradeoff - it's a performance bug that will break large sites.**

**Thank you to the external reviewer for catching this!**

---

## Effort & Risk Legend

**Effort:**
- 🟢 **LOW** (1-2 hours) - Simple code changes, minimal testing
- 🟡 **MEDIUM** (4-8 hours) - Moderate complexity, requires testing
- 🔴 **HIGH** (1-2 days) - Complex changes, extensive testing required

**Risk of Breaking Change:**
- 🟢 **VERY LOW** - Debug/logging only, no functional impact
- 🟡 **MEDIUM** - Critical path or UX impact, needs careful testing
- 🔴 **HIGH** - Core functionality, high chance of regressions

---

**Reviewed by:** AI Agent
**Date:** 2026-01-28
**Version:** v1.2.5