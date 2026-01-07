# Phase 2 Testing Guide

**Purpose**: Verify the refactored search architecture works correctly  
**Date**: 2026-01-06  
**Estimated Time**: 30 minutes

---

## 🎯 Critical Tests

### Test 1: Name Splitting (CRITICAL FIX)

**What We're Testing**: The bug fix for "John Smith" searches

**Steps**:
1. Go to WooCommerce → Customer Search
2. Search for "John Smith" (or any first + last name)
3. Verify results show users with:
   - first_name = "John" AND last_name = "Smith"
   - OR first_name = "Smith" AND last_name = "John" (reversed)

**Expected Result**:
- ✅ Should find matching customers
- ✅ Should work even if `wc_customer_lookup` table is missing

**Previous Behavior** (BROKEN):
- ❌ Searched for "John Smith" as single string
- ❌ Missed users with separate first/last names

**How to Verify Fix**:
```php
// Check debug log for:
// - strategy: "customer_lookup" with mode: "name_pair_prefix"
// - OR strategy: "wp_user_query" with proper meta_query
```

---

### Test 2: Strategy Selection

**What We're Testing**: Automatic strategy selection

**Steps**:
1. Search for any term
2. Check debug log for strategy used
3. Rename `wc_customer_lookup` table temporarily
4. Search again
5. Check debug log for fallback strategy

**Expected Result**:
- ✅ First search uses "customer_lookup" (priority 100)
- ✅ Second search uses "wp_user_query" (priority 50)
- ✅ Both searches return results

**How to Verify**:
```php
// Check debug log for:
// [KISS_WOO_COS] search_customers {"path":"customer_lookup",...}
// [KISS_WOO_COS] search_customers {"path":"wp_user_query",...}
```

---

### Test 3: Memory Monitoring

**What We're Testing**: Memory limit enforcement

**Steps**:
1. Lower memory limit to 1MB (for testing):
   ```php
   // In KISS_Woo_COS_Search::__construct()
   $this->memory_monitor = new Hypercart_Memory_Monitor( 1 * 1024 * 1024 ); // 1MB
   ```
2. Search for a term
3. Check for memory exception

**Expected Result**:
- ✅ Should throw exception if memory exceeds 1MB
- ✅ Should log memory stats
- ✅ Should NOT crash PHP

**How to Verify**:
```php
// Check debug log for:
// [KISS_WOO_COS] search_customers_error {"error":"Memory limit exceeded: 1.5 MB used (limit: 1 MB)",...}
```

---

### Test 4: Email Search

**What We're Testing**: Email detection and search

**Steps**:
1. Search for "john@example.com"
2. Search for "john@" (partial email)
3. Search for "@example.com" (domain)

**Expected Result**:
- ✅ Full email: Uses prefix search on email column
- ✅ Partial email: Falls back to contains search
- ✅ Domain: Uses contains search

**How to Verify**:
```php
// Check debug log for:
// - mode: "prefix_multi_column" (for full email)
// - mode: "contains_email_fallback" (for partial email)
```

---

## 🔍 Debug Logging

### Enable Debug Mode

Add to `wp-config.php`:
```php
define( 'KISS_WOO_COS_DEBUG', true );
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

### Check Debug Log

```bash
tail -f wp-content/debug.log | grep KISS_WOO_COS
```

### Expected Log Format

```
[KISS_WOO_COS] search_customers {
  "term": "John Smith",
  "path": "customer_lookup",
  "lookup_debug": {
    "enabled": true,
    "mode": "name_pair_prefix",
    "table": "wp_wc_customer_lookup",
    "hit": true,
    "count": 5
  },
  "results_users": 5,
  "elapsed_ms": 12.34
}
```

---

## 🧪 Unit Testing (Future)

### Test Files to Create

1. **`test-hypercart-search-term-normalizer.php`**
   - Test name splitting
   - Test email detection
   - Test validation

2. **`test-hypercart-customer-lookup-strategy.php`**
   - Test name pair search
   - Test single term search
   - Test email fallback

3. **`test-hypercart-wp-user-query-strategy.php`**
   - Test name pair search (CRITICAL)
   - Test single term search
   - Test meta_query structure

4. **`test-hypercart-memory-monitor.php`**
   - Test memory tracking
   - Test limit enforcement
   - Test stats reporting

---

## 📊 Performance Benchmarks

### Baseline Metrics (Before Refactoring)

- Search time: ~50-200ms (customer_lookup)
- Search time: ~500-2000ms (WP_User_Query fallback)
- Memory usage: Unknown (no monitoring)
- Query count: Unknown (no counting)

### Target Metrics (After Refactoring)

- Search time: <200ms (customer_lookup)
- Search time: <2000ms (WP_User_Query fallback)
- Memory usage: <50MB (monitored)
- Query count: <10 (to be validated)

### How to Benchmark

```php
// Already instrumented in search_customers():
// - $t0 = microtime(true) at start
// - $elapsed_ms = (microtime(true) - $t0) * 1000 at end
// - Logged in debug output
```

---

## ✅ Acceptance Criteria

### Must Pass
- [ ] "John Smith" search finds users with first=John, last=Smith
- [ ] Strategy selection works (customer_lookup → wp_user_query fallback)
- [ ] Memory monitoring prevents crashes
- [ ] Email search works (full, partial, domain)
- [ ] No PHP errors or warnings
- [ ] Debug logging shows correct strategy and mode

### Should Pass
- [ ] Search time <200ms for customer_lookup
- [ ] Search time <2000ms for wp_user_query
- [ ] Memory usage <50MB
- [ ] No duplicate results

### Nice to Have
- [ ] Unit tests passing
- [ ] Performance benchmarks documented
- [ ] Code coverage >80%

---

## 🚨 Known Issues

### Issue 1: Guest Order Strategy Not Implemented
- **Status**: Planned for Phase 2
- **Workaround**: Guest orders still use old code path
- **Impact**: Low (guest orders are rare)

### Issue 2: No Result Deduplication
- **Status**: Planned for Phase 2
- **Workaround**: None needed (strategies don't overlap)
- **Impact**: Low (unlikely to have duplicates)

---

**Next Steps**: Run tests, fix any issues, implement guest order strategy

