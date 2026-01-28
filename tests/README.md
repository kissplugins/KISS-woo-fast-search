# KISS Woo Fast Search - Test Suite

## Quick Start

### 1. Install Dependencies

```bash
composer install
```

This will install:
- PHPUnit 9.6
- Brain\Monkey (WordPress function mocking)
- Mockery (object mocking)

### 2. Run Tests

```bash
# Run all tests
composer test

# Or use PHPUnit directly
./vendor/bin/phpunit

# Run with test names (recommended)
./vendor/bin/phpunit --testdox

# Run specific test file
./vendor/bin/phpunit tests/Unit/OrderResolverTest.php
./vendor/bin/phpunit tests/Unit/SearchTest.php
./vendor/bin/phpunit tests/Unit/AjaxHandlerTest.php
```

### 3. Expected Output

```
PHPUnit 9.6.x by Sebastian Bergmann and contributors.

Order Resolver
 ✔ Resolve returns null for invalid input
 ✔ Resolve finds order by id
 ✔ Resolve finds order by sequential number

Search
 ✔ Search customers returns empty array for empty term
 ✔ Search customers returns empty array for whitespace term
 ✔ Search customers returns correct structure
 ✔ Search customers uses display name as fallback
 ✔ Search customers escapes html in output
 ✔ Search customers returns empty when no users found
 ✔ Search customers uses user email as primary

Ajax Handler
 ✔ Order number search returns redirect url for valid order
 ✔ Non order search does not set redirect flag
 ✔ Invalid order number returns no redirect
 ✔ Response includes all required fields
 ✔ Short search term returns error
 ✔ Sequential order number resolves correctly

Time: 00:00.123, Memory: 10.00 MB

OK (16 tests, 45 assertions)
```

---

## Test Structure

```
tests/
├── bootstrap.php           # Test setup, loads real plugin classes
├── Unit/
│   ├── OrderResolverTest.php   # Tests order number resolution
│   ├── SearchTest.php          # Tests customer search (real method)
│   └── AjaxHandlerTest.php     # Tests AJAX handler (end-to-end)
├── TESTING-IMPROVEMENTS-SUMMARY.md  # Details on recent improvements
└── README.md               # This file
```

---

## What's Being Tested

### OrderResolverTest
- Order number pattern matching
- Cache behavior
- Order resolution from various sources
- Invalid input handling

### SearchTest
- **Tests the REAL `search_customers()` method**
- Customer search output structure
- XSS protection (HTML escaping)
- Empty result handling
- Email priority logic

### AjaxHandlerTest (NEW!)
- **Complete order number lookup → redirect URL flow**
- JSON response structure validation
- Redirect flag behavior
- Sequential order number support
- Input validation
- Error handling

---

## Key Testing Principles

### 1. Load Real Classes
The test bootstrap loads actual plugin classes from `includes/`, not fake versions.

### 2. Stub Only Dependencies
Tests stub only external dependencies (database, WordPress functions), not the methods being tested.

### 3. Test User-Facing Behavior
`AjaxHandlerTest` validates the complete feature as users experience it, not just isolated components.

---

## Troubleshooting

### "Class not found" errors
Make sure you've run `composer install` to install dependencies.

### "No such file or directory: vendor/bin/phpunit"
Run `composer install` first.

### Tests fail with WordPress function errors
Check that Brain\Monkey is properly installed and the bootstrap file is loading correctly.

### Mock expectations not met
Ensure Mockery is installed and `Mockery::close()` is called in `tearDown()`.

---

## Writing New Tests

### Example Test

```php
<?php
namespace KISS\Tests\Unit;

class MyNewTest extends \KISS_Test_Case {

    protected function setUp(): void {
        parent::setUp();
        // Your setup code
    }

    protected function tearDown(): void {
        \Mockery::close();
        parent::tearDown();
    }

    public function test_something(): void {
        // Arrange
        $input = 'test';
        
        // Act
        $result = some_function( $input );
        
        // Assert
        $this->assertSame( 'expected', $result );
    }
}
```

### Available Assertions

PHPUnit provides many assertions:
- `assertSame()` - Strict equality
- `assertEquals()` - Loose equality
- `assertTrue()` / `assertFalse()`
- `assertNull()` / `assertNotNull()`
- `assertEmpty()` / `assertNotEmpty()`
- `assertCount()`
- `assertArrayHasKey()`
- `assertStringContainsString()`
- And many more...

---

## CI/CD Integration

The test suite is designed to run in CI/CD pipelines. See `.github/workflows/tests.yml` for GitHub Actions configuration.

---

## Coverage Reports

Generate HTML coverage report:

```bash
composer test-coverage
```

View the report:
```bash
open coverage/index.html
```

---

## Further Reading

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Brain\Monkey Documentation](https://giuseppe-mazzapica.gitbook.io/brain-monkey/)
- [Mockery Documentation](http://docs.mockery.io/)

