# Unit Testing Improvements Summary

## Overview

This document summarizes the three major improvements made to the unit testing infrastructure for the KISS Woo Fast Search plugin.

---

## 1. ✅ Load Real Plugin Classes (Stop Faking in bootstrap.php)

### What Changed
- **Before**: `tests/bootstrap.php` defined fake/mock versions of plugin classes
- **After**: Bootstrap now loads the actual plugin classes from `includes/` directory

### Files Modified
- `tests/bootstrap.php`

### Changes Made
```php
// Define plugin constants
define( 'KISS_WOO_COS_VERSION', '1.1.6' );
define( 'KISS_WOO_COS_PATH', dirname( __DIR__ ) . '/' );

// Load real plugin classes in dependency order
require_once KISS_WOO_COS_PATH . 'includes/class-kiss-woo-debug-tracer.php';
require_once KISS_WOO_COS_PATH . 'includes/class-kiss-woo-search-cache.php';
require_once KISS_WOO_COS_PATH . 'includes/class-kiss-woo-order-formatter.php';
require_once KISS_WOO_COS_PATH . 'includes/class-kiss-woo-order-resolver.php';
require_once KISS_WOO_COS_PATH . 'includes/class-kiss-woo-search.php';
```

### Benefits
- Tests now validate actual production code
- No risk of fake classes drifting from real implementations
- Catches real integration issues between classes
- Reduced maintenance burden

### What's Still Mocked
- `WP_User_Query` - WordPress core class (not part of our plugin)
- WordPress functions via Brain\Monkey (e.g., `esc_html`, `wp_json_encode`)
- WooCommerce functions (e.g., `wc_get_order`, `wc_price`)

---

## 2. ✅ Rewrite SearchTest to Test Real search_customers() Method

### What Changed
- **Before**: `Testable_Search` class completely overrode `search_customers()` method
- **After**: `Testable_Search` only stubs external dependencies, tests run against real method

### Files Modified
- `tests/Unit/SearchTest.php`

### Key Improvements

#### Testable_Search Class (Simplified)
```php
class Testable_Search extends \KISS_Woo_COS_Search {
    // Only stub database/external dependencies
    protected function search_user_ids_via_customer_lookup( $term, $limit = 20 ) {
        return $this->stubbed_user_ids;
    }
    
    protected function get_user_meta_for_users( $user_ids, $meta_keys ) {
        return $this->stubbed_billing_meta;
    }
    
    protected function get_order_counts_for_customers( $user_ids ) {
        return $this->stubbed_order_counts;
    }
    
    protected function get_recent_orders_for_customers( $user_ids ) {
        return $this->stubbed_recent_orders;
    }
}
```

#### Test Example
```php
public function test_search_customers_returns_correct_structure(): void {
    // Stub customer lookup
    $this->search->stubbed_user_ids = [ 101 ];
    
    // Mock WP_User_Query to return test user
    $mock_user = (object) [
        'ID'              => 101,
        'user_email'      => 'johndoe@example.com',
        'display_name'    => 'John Doe',
        'user_registered' => '2024-01-15 10:00:00',
    ];
    
    $user_query_mock = Mockery::mock('overload:WP_User_Query');
    $user_query_mock->shouldReceive('get_results')
        ->andReturn( [ $mock_user ] );
    
    // Stub billing meta
    $this->search->stubbed_billing_meta = [ 101 => [...] ];
    
    // Call REAL search_customers() method
    $results = $this->search->search_customers( 'john' );
    
    // Assert results
    $this->assertCount( 1, $results );
    $this->assertSame( 'John Doe', $results[0]['name'] );
}
```

### Benefits
- Tests the actual search algorithm and business logic
- Validates edge cases in real code paths
- Catches bugs in query building, result formatting, etc.
- Tests integration between internal methods

---

## 3. ✅ Add AJAX Handler Tests for Order Number Lookup Feature

### What Changed
- **Before**: Only `OrderResolverTest` existed (tested resolver in isolation)
- **After**: New `AjaxHandlerTest` tests the complete user-facing feature

### Files Created
- `tests/Unit/AjaxHandlerTest.php`

### Test Coverage

#### 1. Valid Order Number → Redirect URL
```php
test_order_number_search_returns_redirect_url_for_valid_order()
```
- Validates `should_redirect_to_order` flag is `true`
- Validates `redirect_url` is set correctly
- Validates `redirect_url` matches order's `view_url`
- Validates order data structure

#### 2. Non-Order Search → No Redirect
```php
test_non_order_search_does_not_set_redirect_flag()
```
- Email search should NOT trigger redirect
- Validates `should_redirect_to_order` is `false`
- Validates `redirect_url` is `null`

#### 3. Invalid Order Number → No Redirect
```php
test_invalid_order_number_returns_no_redirect()
```
- Order not found should NOT trigger redirect
- Validates empty `orders` array

#### 4. Response Structure Validation
```php
test_response_includes_all_required_fields()
```
- Validates all required fields exist
- Validates correct data types

#### 5. Input Validation
```php
test_short_search_term_returns_error()
```
- Validates minimum 2 character requirement

#### 6. Sequential Order Numbers
```php
test_sequential_order_number_resolves_correctly()
```
- Tests integration with Sequential Order Numbers Pro plugin
- Validates `B349445` → order ID `1256171` resolution

### Benefits
- Tests the complete feature as users experience it
- Validates JSON response structure
- Catches integration bugs between components
- Documents expected behavior at the API level
- Provides regression protection for critical redirect feature

---

## Running the Tests

### Install Dependencies
```bash
composer install
```

### Run All Tests
```bash
composer test
# or
./vendor/bin/phpunit
```

### Run with Test Names
```bash
./vendor/bin/phpunit --testdox
```

### Run Specific Test File
```bash
./vendor/bin/phpunit tests/Unit/AjaxHandlerTest.php
```

---

## Next Steps (Optional Enhancements)

1. **Integration Tests for Database Queries**
   - Validate SQL query structure
   - Test HPOS vs Legacy table selection
   - Test `remove_placeholder_escape()` handling

2. **Test Data Builders**
   - Create helper classes for test data
   - Example: `OrderBuilder::create()->withId(123)->build()`

3. **Snapshot Testing for JSON Responses**
   - Catch unexpected changes in API responses

4. **Performance Tests**
   - Validate search performance under load
   - Memory usage tests

---

## Summary

All three improvements have been successfully implemented:

✅ **Task 1**: Bootstrap loads real plugin classes  
✅ **Task 2**: SearchTest tests real `search_customers()` method  
✅ **Task 3**: AjaxHandlerTest validates complete order lookup feature  

The test suite now provides:
- **Higher confidence** - Tests validate actual production code
- **Better coverage** - Tests the complete user-facing feature
- **Easier maintenance** - No fake classes to keep in sync
- **Regression protection** - Catches bugs before they reach production

