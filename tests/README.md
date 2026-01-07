# Hypercart Woo Fast Search - Test Suite

## Overview

This test suite provides comprehensive testing and benchmarking for the Hypercart Woo Fast Search plugin refactoring (v2.0).

## Test Structure

```
tests/
├── fixtures/
│   └── class-hypercart-test-data-factory.php  # Test data generation
├── unit/                                       # Unit tests (TBD)
├── integration/                                # Integration tests (TBD)
├── class-hypercart-performance-benchmark.php   # Performance benchmarking
├── run-benchmarks.php                          # Benchmark runner
└── README.md                                   # This file
```

## Performance Benchmarks

### Running Benchmarks

**From command line:**
```bash
# Run all benchmark scenarios
php tests/run-benchmarks.php

# Run quick benchmark (single scenario)
php tests/run-benchmarks.php --quick
```

**From WordPress admin:**
```php
// Load and run from admin page
require_once HYPERCART_PLUGIN_DIR . '/tests/run-benchmarks.php';
hypercart_run_all_benchmarks();
```

### Performance Gates

All benchmarks must pass these gates:

| Gate | Requirement | Why |
|------|-------------|-----|
| **Speed** | 10x faster than stock WC | Core value proposition |
| **Queries** | <10 database queries | Prevent N+1 problems |
| **Memory** | <50MB peak usage | Prevent exhaustion |
| **Time** | <2 seconds total | User experience |

### Benchmark Scenarios

The test suite includes these scenarios:

1. **Two-word name search** - "John Smith" (the bug case)
2. **First name only** - "John"
3. **Last name only** - "Smith"
4. **Email search** - "john.smith@example.com"
5. **Partial email** - "john.smith"
6. **Single name** - "Madonna"
7. **Three-word name** - "Mary Jane Watson"
8. **Special characters** - "O'Connor"
9. **No match** - "NonExistent Person"

### Comparison Metrics

Each benchmark compares:

- **Stock WooCommerce** - `WC_Customer_Data_Store::search_customers()`
- **Stock WordPress** - `WP_User_Query` with meta queries
- **Hypercart Current** - Our optimized implementation

Metrics tracked:
- Database query count
- Total execution time
- Peak memory usage
- Result count
- Success/error status

## Test Data Fixtures

### Customer Scenarios

The `Hypercart_Test_Data_Factory` provides:

- **8 customer scenarios** - Edge cases and common patterns
- **2 guest order scenarios** - Guest checkout testing
- **Large dataset generator** - Performance testing with 1000+ customers
- **Search scenarios** - Expected results for each search term

### Creating Test Data

```php
// Get customer test scenarios
$customers = Hypercart_Test_Data_Factory::create_test_customers();

// Get guest order scenarios
$guests = Hypercart_Test_Data_Factory::create_guest_orders();

// Generate large dataset for performance testing
$large_dataset = Hypercart_Test_Data_Factory::create_large_dataset( 1000 );

// Get search test scenarios
$scenarios = Hypercart_Test_Data_Factory::get_search_scenarios();
```

## Unit Tests (Phase 1.2 - TBD)

Unit tests will cover:

- Search term parsing
- Query building
- Result hydration
- Edge cases and error handling

## Integration Tests (Phase 1.3 - TBD)

Integration tests will cover:

- End-to-end search scenarios
- HPOS compatibility
- WooCommerce integration
- WordPress integration

## Development Workflow

### Phase 1: Stabilization (Current)

1. ✅ Create test data fixtures
2. ⏳ Write unit tests for current behavior
3. ⏳ Create integration test suite
4. ✅ Build performance benchmark harness
5. ⏳ Document baseline metrics
6. ⏳ Add memory monitoring
7. ⏳ Create circuit breaker
8. ⏳ Verify all tests pass

### Phase 2-4: Refactoring

After each refactoring phase:

1. Run unit tests - ensure behavior unchanged
2. Run integration tests - ensure no regressions
3. Run benchmarks - ensure performance gates pass
4. Document metrics - track improvements

## Expected Performance

### Current Implementation (v1.x)

- ~100 queries per search (N+1 problem)
- ~1-2 seconds per search
- ~10-20MB memory usage
- Still 10x faster than stock WC

### Target Implementation (v2.0)

- **<10 queries per search** (batch loading)
- **<0.5 seconds per search** (optimized queries)
- **<10MB memory usage** (efficient hydration)
- **10-30x faster than stock WC** (maintained advantage)

## Troubleshooting

### Benchmarks Failing

If benchmarks fail:

1. Check query count - Look for N+1 problems
2. Check memory usage - Look for large result sets
3. Check execution time - Look for slow queries
4. Review query log - Identify bottlenecks

### WordPress Not Found

If `run-benchmarks.php` can't find WordPress:

```php
// Adjust paths in run-benchmarks.php
$wp_load_paths = [
    __DIR__ . '/../../../../wp-load.php',  // Standard
    '/path/to/your/wp-load.php',           // Custom
];
```

## Contributing

When adding tests:

1. Add test scenarios to `Hypercart_Test_Data_Factory`
2. Add benchmark scenarios to `get_search_scenarios()`
3. Document expected behavior
4. Ensure tests pass before and after refactoring

## License

Same as parent plugin (see LICENSE file in root directory)

