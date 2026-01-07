# Performance Tests Admin Page

## Overview

The Performance Tests admin page provides a comprehensive benchmarking tool accessible directly from the WordPress admin interface. It compares Hypercart Fast Search against stock WooCommerce and WordPress search implementations.

## Location

**WooCommerce → Performance Tests**

## Features

### 1. Run Benchmarks
- Select from 9 pre-defined search scenarios
- Execute comprehensive performance tests with one click
- Compare against stock WooCommerce and WordPress implementations

### 2. Performance Metrics
Each benchmark measures:
- **Query Count**: Number of database queries executed
- **Execution Time**: Total time in seconds
- **Memory Usage**: Peak memory consumption
- **Result Count**: Number of results returned
- **Improvement Ratios**: Speed, query reduction, and memory savings

### 3. Performance Gates
Automated pass/fail checks for:
- ✅ Must be at least 10x faster than stock WooCommerce
- ✅ Must use less than 10 database queries
- ✅ Must use less than 50MB memory
- ✅ Must complete in less than 2 seconds

### 4. Historical Tracking
- Stores last 50 benchmark results in `wp_options` table
- View performance trends over time
- Compare before/after refactoring
- Export data for documentation

### 5. Visual Results
- Color-coded pass/fail indicators
- Improvement badges showing performance gains
- Detailed comparison tables
- Historical summary table

## Usage

### Running a Benchmark

1. Navigate to **WooCommerce → Performance Tests**
2. Select a test scenario from the dropdown
3. Click **Run Performance Test**
4. View results immediately

### Test Scenarios

Available scenarios include:
- Two-word name search (e.g., "John Smith")
- Single name search
- Email search
- Partial email search
- Special characters in names
- Hyphenated names
- Accented characters
- Common names (high result count)
- Non-existent search (zero results)

### Interpreting Results

#### Performance Comparison Table
Shows side-by-side metrics for:
- Stock WooCommerce search
- Stock WordPress user search
- Hypercart Fast Search (highlighted in green)

#### Improvement Section
Displays multiplier improvements:
- Query reduction (e.g., "5.2x fewer queries")
- Speed improvement (e.g., "12.3x faster")
- Memory reduction (e.g., "3.1x less memory")

#### Performance Gates
Visual indicators showing pass/fail status for each gate with actual values.

#### Test History
Table showing recent test runs with:
- Date/time
- Scenario tested
- Key metrics
- Gates passed (e.g., "4/4")

## Data Storage

### Option Name
`kiss_woo_benchmark_results`

### Data Structure
```php
array(
    array(
        'timestamp' => '2026-01-06 10:30:00',
        'scenario_key' => 'two_word_name',
        'scenario' => array(...),
        'stock_wc_search' => array(
            'query_count' => 45,
            'total_time' => 1.234,
            'memory_peak' => 12345678,
            'result_count' => 5
        ),
        'stock_wp_user_search' => array(...),
        'hypercart_current' => array(...),
        'improvement_vs_stock_wc' => array(
            'query_reduction' => 5.2,
            'speed_improvement' => 12.3,
            'memory_reduction' => 3.1
        ),
        'user_id' => 1,
        'version' => '1.0.3'
    ),
    // ... up to 50 results
)
```

### Clearing History
Click **Clear History** button to remove all stored benchmark results.

## Integration

### File Structure
- `admin/class-kiss-woo-performance-tests.php` - Main admin page class
- `tests/class-hypercart-performance-benchmark.php` - Benchmark harness
- `tests/fixtures/class-hypercart-test-data-factory.php` - Test scenarios

### Initialization
The page is automatically registered when the plugin loads:
```php
KISS_Woo_COS_Performance_Tests::instance();
```

### Security
- Nonce verification on all form submissions
- Capability check: `manage_woocommerce`
- Sanitized inputs
- Escaped outputs

## Use Cases

### 1. Baseline Documentation
Run benchmarks before refactoring to establish baseline performance metrics.

### 2. Regression Testing
Run benchmarks after code changes to ensure performance hasn't degraded.

### 3. Performance Validation
Verify that performance gates are met before deploying to production.

### 4. Optimization Tracking
Track performance improvements across multiple refactoring iterations.

### 5. Client Reporting
Export benchmark results to demonstrate performance improvements to stakeholders.

## Tips

- Run benchmarks multiple times to account for caching and variability
- Test different scenarios to ensure consistent performance across use cases
- Clear WordPress object cache before running benchmarks for accurate results
- Use the history feature to track performance trends over time
- Export results before major refactoring for comparison

## Troubleshooting

### Benchmark Takes Too Long
- Check for slow database queries
- Verify database indexes are in place
- Consider reducing test data size

### Inconsistent Results
- Clear all caches before testing
- Run multiple times and average results
- Check for background processes affecting performance

### Memory Errors
- Increase PHP memory limit in wp-config.php
- Reduce test data size
- Check for memory leaks in search code

## Next Steps

After establishing baseline metrics:
1. Document current performance in project documentation
2. Identify bottlenecks from benchmark results
3. Plan refactoring strategy based on metrics
4. Re-run benchmarks after each optimization
5. Verify all performance gates pass before deployment

