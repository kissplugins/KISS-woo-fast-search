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

**Location:** `class-kiss-woo-coupon-search.php` (line 70-88)

**Severity:** ⚠️ **LOW-MEDIUM** (Performance)

**Issue:**
```php
WHERE blog_id = %d
  AND status NOT IN ('trash', 'auto-draft')
  AND (
      code_normalized LIKE %s       -- ✅ Prefix search (index-friendly)
      OR title LIKE %s              -- ✅ Prefix search (index-friendly)
      OR description_normalized LIKE %s  -- ❌ Infix search (full scan)
  )
```

**Analysis:**
- ⚠️ **PARTIALLY AGREE** - Description search uses `%term%` (infix), which can't use indexes
- ✅ **BUT** - Code and title use prefix search (index-friendly)
- ✅ **BUT** - This is an **intentional UX tradeoff**:
  - Users expect to find "SUMMER" in "BIGSUMMER2024"
  - Admin search with 100k coupons is acceptable for this use case
  - Lookup table is already optimized vs. wp_posts meta queries

**Proposed Fix (Optional):**
If performance becomes an issue, add full-text index:

```sql
-- Option 1: Add FULLTEXT index (MySQL 5.6+)
ALTER TABLE wp_kiss_woo_coupon_lookup
ADD FULLTEXT INDEX idx_description_fulltext (description_normalized);

-- Then use MATCH AGAINST instead of LIKE
WHERE MATCH(description_normalized) AGAINST(%s IN BOOLEAN MODE)
```

**OR** limit description search to prefix-only:

```php
// Change from:
OR description_normalized LIKE %s  -- %term%

// To:
OR description_normalized LIKE %s  -- term%
```

**Impact:** Medium - Changes user experience (can't find "SUMMER" in middle of text)

**Priority:** Low - Monitor performance first, optimize only if needed

**Recommendation:** ❌ **DO NOT FIX** - Current behavior is acceptable for admin search

**Effort:** 🟡 **MEDIUM** (4-8 hours)
- Add FULLTEXT index to lookup table
- Rewrite query to use MATCH AGAINST
- Test with 100k+ coupons to verify performance improvement
- OR: Change to prefix-only search (2 hours, but degrades UX)

**Risk of Breaking Change:** 🟡 **MEDIUM**
- FULLTEXT index: Low risk, but requires MySQL 5.6+ (most sites have this)
- Prefix-only search: **High UX impact** - users can't find "SUMMER" in middle of text
- Could break existing user workflows/expectations

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
| #2 - LIKE scans on description | Low | ⚠️ Partial | Low | 🟡 Medium (4-8h) | 🟡 Medium (UX impact) | Monitor, don't fix yet |
| #3 - Fallback N+1 queries | High | ✅ Yes | High | 🟡 Medium (4-6h) | 🟡 Medium (critical path) | Batch load meta OR accept as temporary |

---

## Recommended Action Plan

### Phase 1: Critical Fixes (Before Production)
1. ✅ **Fix #1**: Wrap all error_log() in WP_DEBUG checks
2. ⚠️ **Fix #3**: Either:
   - Option A: Batch load coupon meta (recommended)
   - Option B: Accept current implementation as temporary (lazy backfill mitigates)

### Phase 2: Monitor & Optimize (Post-Launch)
3. 📊 **Monitor #2**: Track query performance on description searches
4. 📊 **Monitor #3**: Track fallback usage (should decrease over time)

---

## Notes

- **User IDs are NOT PII in isolation** - Only useful to trusted parties with DB access
- **Finding #1** is rated Medium because it's poor practice, not a security risk
- **Finding #2** is an intentional UX tradeoff, not a bug
- **Finding #3** is mitigated by lazy backfill strategy
- All findings are in `feature/add-coupon-search` branch, not yet in `main`

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