# Admin Benchmark Page - Quick Start Guide

## 🎯 Purpose

Run comprehensive performance benchmarks directly from WordPress admin to:
- Establish baseline performance metrics
- Compare Hypercart vs Stock WooCommerce vs Stock WordPress
- Track performance over time
- Validate performance gates

## 📍 Location

Navigate to: **WooCommerce → Performance Tests**

## 🚀 Quick Start

### Step 1: Access the Page
1. Log into WordPress admin
2. Go to **WooCommerce** menu
3. Click **Performance Tests**

### Step 2: Run Your First Benchmark
1. Select a test scenario (default: "Two-word name search")
2. Click **Run Performance Test**
3. Wait 5-30 seconds for results

### Step 3: Review Results
You'll see:
- ✅ Performance comparison table
- ✅ Improvement metrics (e.g., "12.3x faster")
- ✅ Performance gates (pass/fail)
- ✅ Historical results

## 📊 What Gets Measured

### For Each Implementation:
- **Queries**: Number of database queries
- **Time**: Execution time in seconds
- **Memory**: Peak memory usage
- **Results**: Number of customers/orders found

### Implementations Compared:
1. **Stock WooCommerce** - `wc_get_orders()` search
2. **Stock WordPress** - `WP_User_Query` search
3. **Hypercart Fast Search** - Our optimized implementation

## ✅ Performance Gates

Tests must pass ALL of these:
- ✅ 10x faster than stock WooCommerce
- ✅ Less than 10 database queries
- ✅ Less than 50MB memory
- ✅ Less than 2 seconds execution time

## 💾 Data Storage

Results are automatically saved to:
- **Option Name**: `kiss_woo_benchmark_results`
- **Storage**: WordPress `wp_options` table
- **Retention**: Last 50 results
- **Format**: JSON array

## 📈 Use Cases

### 1. Establish Baseline (Do This First!)
```
1. Run benchmark with "Two-word name search"
2. Note the results
3. Save/screenshot for documentation
4. This is your "before refactoring" baseline
```

### 2. Validate Refactoring
```
1. Make code changes
2. Run same benchmark scenario
3. Compare with baseline
4. Verify all gates still pass
```

### 3. Track Progress
```
1. Run benchmarks regularly
2. View history table
3. Monitor trends over time
4. Identify regressions early
```

### 4. Test Edge Cases
```
1. Run all 9 scenarios
2. Verify consistent performance
3. Identify problematic patterns
4. Optimize weak spots
```

## 🎨 Test Scenarios

| Scenario | Description | Tests |
|----------|-------------|-------|
| Two-word name | "John Smith" | Common search pattern |
| Single name | "John" | Partial match |
| Email search | "john@example.com" | Exact email |
| Partial email | "john@" | Email prefix |
| Special chars | "O'Brien" | Apostrophes |
| Hyphenated | "Mary-Jane" | Hyphens |
| Accented | "José García" | Unicode |
| Common name | "Smith" | High result count |
| Non-existent | "XYZ999" | Zero results |

## 🔧 Troubleshooting

### Benchmark Fails
- Check PHP memory limit (increase if needed)
- Verify WooCommerce is active
- Check for database connection issues

### Slow Performance
- Clear WordPress object cache
- Check database indexes
- Review slow query log

### Inconsistent Results
- Run multiple times
- Clear caches between runs
- Check for background processes

## 📝 Exporting Results

### Manual Export
1. Run benchmark
2. Open browser DevTools (F12)
3. Go to Application → Storage → Options
4. Find `kiss_woo_benchmark_results`
5. Copy JSON value

### Programmatic Export
```php
$results = get_option( 'kiss_woo_benchmark_results', array() );
file_put_contents( 'baseline-metrics.json', json_encode( $results, JSON_PRETTY_PRINT ) );
```

## 🎯 Recommended Workflow

### Before Refactoring
1. ✅ Run all 9 test scenarios
2. ✅ Document baseline metrics
3. ✅ Save results to file
4. ✅ Note any failing gates

### During Refactoring
1. ✅ Run benchmarks after each major change
2. ✅ Compare with baseline
3. ✅ Verify gates still pass
4. ✅ Track improvements

### After Refactoring
1. ✅ Run full test suite
2. ✅ Compare final vs baseline
3. ✅ Document improvements
4. ✅ Celebrate wins! 🎉

## 🔒 Security

- ✅ Nonce verification on all actions
- ✅ Capability check: `manage_woocommerce`
- ✅ Sanitized inputs
- ✅ Escaped outputs

## 📚 Related Files

- `admin/class-kiss-woo-performance-tests.php` - Admin page
- `tests/class-hypercart-performance-benchmark.php` - Benchmark engine
- `tests/fixtures/class-hypercart-test-data-factory.php` - Test data
- `admin/README-PERFORMANCE-TESTS.md` - Detailed documentation

## 💡 Pro Tips

1. **Run benchmarks in production-like environment** for accurate results
2. **Clear all caches** before running benchmarks
3. **Run multiple times** and average results
4. **Test different scenarios** to ensure consistent performance
5. **Document baseline** before making any changes
6. **Track history** to identify performance regressions
7. **Export results** for stakeholder reporting

## 🎉 Success Criteria

You'll know it's working when:
- ✅ All 4 performance gates pass
- ✅ Hypercart is 10-30x faster than stock WC
- ✅ Query count is under 10
- ✅ Memory usage is reasonable
- ✅ Results are accurate and complete

## 🚨 Red Flags

Watch out for:
- ❌ Any performance gate failing
- ❌ Slower than stock WooCommerce
- ❌ More than 10 database queries
- ❌ Memory usage over 50MB
- ❌ Execution time over 2 seconds
- ❌ Incorrect result counts

## Next Steps

1. **Run your first benchmark now!**
2. Document the baseline metrics
3. Review the results
4. Plan your refactoring strategy
5. Re-run after each optimization

---

**Questions?** Check `admin/README-PERFORMANCE-TESTS.md` for detailed documentation.

