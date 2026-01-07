# Refactoring Analysis - Current Implementation

**Date**: 2026-01-06  
**File**: `includes/class-kiss-woo-search.php`  
**Status**: Analysis Complete - Ready to Refactor

---

## 📊 Current Implementation Analysis

### What's GOOD (Keep This) ✅

1. **Batch Query Engine** (Lines 312-351, 463-487, 648-760)
   - ✅ `get_user_meta_for_users()` - Batches user meta in ONE query
   - ✅ `get_order_counts_hpos()` - Batches order counts in ONE query
   - ✅ `get_recent_orders_for_customers()` - Batches recent orders
   - **Result**: Already avoiding N+1 queries!

2. **Customer Lookup Table** (Lines 196-302)
   - ✅ Uses `wc_customer_lookup` indexed table
   - ✅ Name splitting works correctly ("John Smith" → first + last)
   - ✅ Email search with prefix matching
   - ✅ Has LIMIT clause (20 results)

3. **Memory Optimization** (Line 71)
   - ✅ Avoids `all_with_meta` (loads only needed fields)
   - ✅ Only fetches 3 meta keys: billing_first_name, billing_last_name, billing_email

4. **HPOS Support** (Lines 374-382, 407-442)
   - ✅ Detects HPOS availability
   - ✅ Falls back to legacy gracefully
   - ✅ Uses `wc_orders` table when available

5. **Debug Logging** (Lines 22-53)
   - ✅ Opt-in debug mode
   - ✅ Tracks which search path was used
   - ✅ Logs performance metrics

### What's BROKEN (Fix This) ❌

1. **WP_User_Query Fallback** (Lines 87-116)
   - ❌ **CRITICAL**: Name splitting BROKEN
   - ❌ Searches "John Smith" as single string in meta_query
   - ❌ Uses OR + LIKE (prevents index usage)
   - ❌ Full table scans on wp_usermeta
   - **Impact**: Slow and inaccurate when lookup table unavailable

2. **No Memory Monitoring**
   - ❌ No memory usage tracking
   - ❌ No circuit breaker
   - ❌ No memory limits enforced
   - **Impact**: Can still crash with large datasets

3. **No Result Limit Enforcement**
   - ❌ Limit is passed but not enforced everywhere
   - ❌ `get_recent_orders_for_customers()` uses `count($user_ids) * 10 * 5` (line 678)
   - ❌ Could load 1000+ orders if 20 users
   - **Impact**: Memory exhaustion risk

4. **Guest Order Search** (Lines 771-800)
   - ❌ Uses `wc_get_orders()` which can be slow
   - ❌ No batch optimization
   - ❌ Separate code path (violates DRY)

---

## 🎯 Refactoring Strategy

### Phase 2.1: Fix Critical Issues (TODAY)

**Priority 1: Fix WP_User_Query Name Splitting**
- Extract name splitting logic to shared normalizer
- Apply to both customer_lookup AND wp_user_query
- Add tests to prevent regression

**Priority 2: Add Memory Safety**
- Add memory monitoring class
- Add circuit breaker pattern
- Enforce strict result limits

**Priority 3: Consolidate Search Logic**
- Create strategy interface
- Wrap existing code in strategies
- Add strategy selector

### Phase 2.2: Optimize & Test (THIS WEEK)

**Priority 4: Test in Isolation**
- Test with production data
- Monitor memory usage
- Validate query counts
- Check response times

**Priority 5: Documentation**
- Document new architecture
- Update CHANGELOG
- Create migration guide

---

## 📐 Architecture Design

### New Structure (Minimal Refactoring)

```
includes/
├── search/
│   ├── class-hypercart-search-term-normalizer.php    # NEW - Extract name splitting
│   ├── interface-hypercart-search-strategy.php       # NEW - Strategy pattern
│   ├── class-hypercart-customer-lookup-strategy.php  # WRAP existing code
│   ├── class-hypercart-wp-user-query-strategy.php    # FIX name splitting
│   └── class-hypercart-guest-order-strategy.php      # WRAP existing code
├── monitoring/
│   ├── class-hypercart-memory-monitor.php            # NEW - Memory tracking
│   └── class-hypercart-circuit-breaker.php           # NEW - Safety limits
└── class-kiss-woo-search.php                         # REFACTOR - Use strategies
```

### Key Principle: **Minimal Changes, Maximum Impact**

Following ARCHITECT.md:
- ✅ **DRY**: Extract name splitting to ONE place
- ✅ **Hypercart**: Only add abstractions that improve clarity
- ✅ **Performance**: Keep existing batch queries (they work!)
- ✅ **Testing**: Add tests around critical behavior
- ✅ **Simplicity**: Wrap existing code, don't rewrite

---

## 🚀 Implementation Plan

### Step 1: Extract Name Normalizer (30 min)
```php
class Hypercart_Search_Term_Normalizer {
    public function normalize($term) {
        return [
            'original' => $term,
            'sanitized' => sanitize_text_field($term),
            'is_email' => is_email($term),
            'name_parts' => $this->split_name($term),
        ];
    }
}
```

### Step 2: Create Strategy Interface (15 min)
```php
interface Hypercart_Search_Strategy {
    public function search($normalized_term, $limit = 20);
    public function is_available();
    public function get_priority();
}
```

### Step 3: Wrap Customer Lookup (30 min)
- Move lines 196-302 into strategy class
- Keep ALL existing logic
- Just wrap it in strategy pattern

### Step 4: Fix WP_User_Query (45 min)
- Move lines 87-116 into strategy class
- ADD name splitting logic
- Test with "John Smith"

### Step 5: Add Memory Monitor (30 min)
```php
class Hypercart_Memory_Monitor {
    public function check_memory($threshold = 50 * 1024 * 1024) {
        $usage = memory_get_usage();
        if ($usage > $threshold) {
            throw new Exception('Memory limit exceeded');
        }
    }
}
```

### Step 6: Update Main Search Class (30 min)
- Use strategy selector
- Add memory monitoring
- Enforce result limits

**Total Time**: ~3 hours

---

## ✅ Success Criteria

### Must Pass
- ✅ Memory usage <50MB
- ✅ Query count <10
- ✅ Response time <2s
- ✅ Name splitting works in ALL paths
- ✅ No crashes with production data

### Tests
- ✅ "John Smith" finds correct users
- ✅ Email search works
- ✅ Guest orders found
- ✅ Memory limit enforced
- ✅ Result limit enforced

---

**Next**: Start with Step 1 - Extract Name Normalizer

