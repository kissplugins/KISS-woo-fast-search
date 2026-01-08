# KISS WooCommerce Fast Search - Tests

## Running Tests

### Unit Tests

Unit tests can be run using WP-CLI:

```bash
# Run order term parsing tests
wp eval-file tests/unit/test-order-term-parsing.php
```

### Benchmark Tests

Benchmarks are available in the WordPress admin:

1. Navigate to **WooCommerce → KISS Benchmark**
2. Enter a search query (email or order number)
3. Click "Run Benchmark"

The benchmark compares:
- WooCommerce stock order search vs KISS order search (by numeric ID)
- WooCommerce stock customer search vs KISS customer search
- First run vs warm (same-request) performance

## Test Coverage

### Unit Tests
- `test-order-term-parsing.php` - Tests for order number parsing logic
  - Numeric IDs (12345, #12345)
  - B/D prefix formats (B349445, #B349445, D349445)
  - Case-insensitivity
  - Invalid inputs (names, emails, partial matches)

### Integration Tests
- Benchmark page tests real-world performance with actual database queries

## Expected Performance

Based on the benchmark results, you should see:

| Test | WooCommerce Stock | KISS Fast Search | Speedup |
|------|------------------|------------------|---------|
| Order ID Search (numeric) | 500-2000ms | 5-20ms | 25-400x faster |
| Warm Order Search | N/A | 1-5ms | Near-instant |
| Customer Search | 500-1500ms | 50-150ms | 3-30x faster |

**Note:** Order search benchmarks use numeric order IDs to test the true fast-path (`wc_get_order($id)`). Sites using non-numeric order numbers (e.g., sequential order plugins with custom formats) will see KISS fail-fast (no match) unless the order number exactly matches the order ID.

## Adding New Tests

To add new unit tests:

1. Create a new file in `tests/unit/`
2. Follow the pattern in `test-order-term-parsing.php`
3. Use WP-CLI `wp eval-file` to run

To add benchmark tests:

1. Edit `admin/class-kiss-woo-benchmark.php`
2. Add new timing sections following existing patterns
3. Update the benchmark page template if needed

