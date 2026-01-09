# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **State Machine**: Implemented explicit finite state machines (FSM) for AJAX search to prevent impossible UI states (Audit item 4.1)
  - Created state machine for admin search page with 5 states: IDLE, SEARCHING, SUCCESS, ERROR, REDIRECTING
  - Created state machine for toolbar search with 4 states: IDLE, SEARCHING, REDIRECTING_ORDER, REDIRECTING_SEARCH
  - Added state transition validation to prevent invalid state changes
  - Added request abortion when starting new searches to prevent race conditions
  - Added double-submission prevention to ignore duplicate requests
  - Added debug logging for state transitions (when debug mode enabled)
  - Created comprehensive documentation in `docs/STATE-MACHINE.md` with state diagrams
  - Benefits: Prevents "Searching..." text from getting stuck, prevents double submissions, clearer error recovery

### Fixed
- **Test Infrastructure**: Fixed all 38 unit tests to pass successfully (100% passing rate)
  - Fixed `WP_User_Query` mocking conflict by removing class definition from bootstrap
  - Added comprehensive `$wpdb` mock to `AjaxHandlerTest` with all required methods
  - Added missing WordPress function stubs (`get_edit_post_link`, `wc_get_order_status_name`, `wp_strip_all_tags`, `wp_list_pluck`, `human_time_diff`)
  - Fixed AJAX response handling by simulating `wp_send_json_*` functions' `die()` behavior with exceptions
  - Added proper `wc_seq_order_number_pro` plugin mock to `OrderResolverTest`
  - Created `patchwork.json` to enable stubbing of internal PHP functions
  - All test suites now pass: AjaxHandler (6/6), OrderResolver (25/25), Search (7/7)

### Changed
- **Code Quality - Single Source of Truth**: Completed high-priority refactoring from systematic audit
  - **Order Formatting**: Removed deprecated `format_order_for_output()` and `format_order_data_for_output()` methods from Search class. All order formatting now goes through `KISS_Woo_Order_Formatter` as the single source of truth (Audit item 3.1)
  - **Debug Logging**: Removed `debug_log()` and `is_debug_enabled()` wrapper methods from Search class. All debug logging now goes directly through `KISS_Woo_Debug_Tracer::log()` for single observability path (Audit item 3.3)
  - **HPOS Detection**: Already using `KISS_Woo_Utils::is_hpos_enabled()` utility across all files (Audit item 2.2 - previously completed)
- **Code Quality - Separation of Concerns**: Completed medium-priority refactoring from systematic audit
  - **Inline CSS/JS Extraction**: Extracted ~400 lines of inline CSS/JS to separate files for better caching and maintainability (Audit item 1.2)
    - Created `admin/css/kiss-woo-admin.css` (77 lines from admin-page.php)
    - Created `admin/css/kiss-woo-debug.css` (103 lines from debug-panel.php)
    - Created `admin/js/kiss-woo-debug.js` (90 lines from debug-panel.php)
    - Created `admin/css/kiss-woo-toolbar.css` (118 lines from toolbar.php)
    - Created `admin/js/kiss-woo-toolbar.js` (107 lines from toolbar.php)
    - Updated all three files to properly enqueue assets via `wp_enqueue_style()` and `wp_enqueue_script()`
    - Removed inline `<style>` and `<script>` tags from PHP files
  - **AJAX Handler Extraction**: Extracted 110+ lines of AJAX business logic to dedicated class for better separation of concerns (Audit item 1.1)
    - Created `includes/class-kiss-woo-ajax-handler.php` with `KISS_Woo_Ajax_Handler` class
    - Moved `handle_ajax_search()` method from main plugin file to new `handle_search()` method in dedicated class
    - Extracted search orchestration logic to private `perform_search()` method for better testability
    - Updated all 6 AJAX handler tests to use new class
    - Main plugin file reduced from 264 to 150 lines (43% reduction)
- **UX Improvement**: Updated search input placeholders to include "order ID" to clarify that order number search is supported
  - Toolbar: "Search order ID, email, or name…"
  - Admin page: "Type order ID, email, or name and hit Enter…"
  - Description text: "Enter an order ID, customer email, partial email, or name to quickly find their account and orders."
- **Test Infrastructure**: Refactored test bootstrap to load real plugin classes instead of fake implementations
- **Test Coverage**: Rewrote `SearchTest` to test the actual `search_customers()` method instead of a stubbed version
- **Test Suite**: Added comprehensive AJAX handler tests (`AjaxHandlerTest`) for end-to-end order number lookup → redirect URL flow
- **Order Output (Single Source of Truth)**: Standardized frontend rendering and tests to use `order_number` as the canonical field.
  - **Legacy (temporary)**: `number` is still provided as an alias for one version to avoid breaking older consumers.
- **Debug Logging**: Consolidated search-class logging through `KISS_Woo_Debug_Tracer` (via a single wrapper) and reduced direct `error_log()` usage.
- **HPOS Detection**: Replaced duplicated `OrderUtil::custom_orders_table_usage_is_enabled()` checks with `KISS_Woo_Utils::is_hpos_enabled()` where applicable.
- **Test Suite (Blocker Fix)**: Updated `tests/bootstrap.php` to load the main plugin file (`kiss-woo-fast-order-search.php`) and stub minimal WP/WC bootstrap functions/classes so unit tests can instantiate `KISS_Woo_Customer_Order_Search_Plugin` without WordPress.

### Added
- **Documentation**: Created `tests/TESTING-IMPROVEMENTS-SUMMARY.md` with detailed explanation of testing improvements
- **Documentation**: Created `tests/README.md` with complete guide on running tests and writing new tests
- **Test Coverage**: Added 6 new test cases in `AjaxHandlerTest` covering:
  - Valid order number → redirect URL validation
  - Non-order search → no redirect behavior
  - Invalid order number handling
  - JSON response structure validation
  - Input validation (minimum 2 characters)
  - Sequential order number resolution
- **Utilities**: Added `KISS_Woo_Utils::is_hpos_enabled()` to centralize HPOS detection.
- **Order Formatting**: Added `KISS_Woo_Order_Formatter::format_from_raw()` so SQL-fetched orders share the same output shape as `format()`.

### Technical Details
- `tests/bootstrap.php` now loads all real plugin classes from `includes/` directory in proper dependency order
- Only external dependencies (WordPress core classes, WooCommerce functions) are mocked via Brain\Monkey
- `Testable_Search` class simplified to only stub database queries, not business logic
- Tests now validate actual production code paths, catching real integration bugs
- All tests use Mockery for `WP_User_Query` mocking to test against real WordPress behavior
- `admin/kiss-woo-admin.js` now prefers `order_number` / `total_display` / `date_display` with safe fallbacks for older payloads.
- `tests/Unit/AjaxHandlerTest.php` now asserts `order_number` and optionally validates legacy `number` when present.

---

## [1.1.6] - 2026-01-09

### Changed
- **Code Quality**: Removed debug code left in production (wrapped `console.log()` calls in debug flag checks)
- **Code Cleanup**: Removed unused methods `get_order_count_for_customer()` and `get_recent_orders_for_customer()` from `KISS_Woo_COS_Search`
- **Code Cleanup**: Removed dead comments from `class-kiss-woo-admin-page.php`
- **Debug Consolidation**: Updated `is_debug_enabled()` to use `KISS_WOO_FAST_SEARCH_DEBUG` constant instead of `KISS_WOO_COS_DEBUG` for consistency
- **Performance**: Consolidated `is_toolbar_hidden()` check to run once in constructor instead of 5 times per request

### Technical Details
- All debug logging now respects the `KISS_WOO_FAST_SEARCH_DEBUG` flag
- Toolbar initialization now short-circuits all hooks if toolbar is hidden, avoiding unnecessary hook registrations
- Removed ~65 lines of unused code and dead comments

---

## [1.1.5] - 2026-01-09

### Added
- **PHPUnit Test Suite**: Added unit tests for `KISS_Woo_Order_Resolver` and `KISS_Woo_COS_Search::search_customers()` to catch regressions during refactoring.
- **GitHub Actions CI**: Automated testing workflow runs on push/PR to main branches. Tests against PHP 7.4, 8.0, 8.1, and 8.2.
- **Test Infrastructure**: Added `composer.json`, `phpunit.xml`, and test bootstrap with Brain\Monkey for WordPress function mocking.

### Technical Details
- Tests use Brain\Monkey and Mockery for WordPress/WooCommerce mocking
- Run tests locally: `composer install && composer test`
- Tests cover: order number pattern matching, cache behavior, order resolution, customer search output structure, and XSS protection

---

## [1.1.4] - 2026-01-09

### Added
- **⚡ Fast Path for Toolbar**: Floating toolbar now performs AJAX search first before redirecting to search page. If searching for an order number, redirects directly to the order editor (saves 4-8 seconds). Falls back to search page for customer searches or if AJAX fails.

### Changed
- **Toolbar UX**: Shows "Searching..." state while performing AJAX lookup
- **Console Logging**: Toolbar now logs search results to console for debugging

### Performance
- **Order searches via toolbar**: ~1-2 seconds (AJAX → direct redirect)
- **Customer searches via toolbar**: ~4-5 seconds (AJAX → search page fallback)
- **Previous behavior**: Always 7-9 seconds (page load → AJAX → redirect)

---

## [1.1.3] - 2026-01-09

### Fixed
- **🎯 CRITICAL: Double-Escape Bug**: Fixed URL corruption issue where `esc_url()` was double-escaping URLs, causing redirects to fail with malformed URLs like `edit.php#038;action=edit` instead of `post.php?post=123&action=edit`. Removed unnecessary `esc_url()` from JSON response data (URLs from `admin_url()` are already safe).

### Technical Details
- **Root Cause**: `esc_url()` was being applied to URLs that were already escaped by WordPress's `admin_url()` function
- **Symptom**: Ampersands (`&`) were being double-encoded to `&#038;` which browsers interpreted as `#038;`, breaking query parameters
- **Fix**: Removed `esc_url()` from `KISS_Woo_Order_Formatter::format()` since the URL is used in JSON/JavaScript context, not HTML output
- **Security**: URLs still safe because they come from `admin_url()`, `get_edit_post_link()`, or `$order->get_edit_order_url()` which are all WordPress core functions that return safe URLs

---

## [1.1.2.2] - 2026-01-09

### Changed
- **Auto-Redirect Re-enabled**: Removed debug intercept mode. Order searches now automatically redirect to the order editor again. Console logging and self-test page remain available for troubleshooting.

---

## [1.1.2.1] - 2026-01-09

### Fixed
- **Browser Cache Issue**: Bumped version number to force browser cache refresh of JavaScript files. Added version logging to console to help identify cached JS issues.

---

## [1.1.2] - 2026-01-09

### Added
- **Self-Test Page**: New diagnostic page under WooCommerce → KISS Self-Test that helps troubleshoot order URL generation and redirect issues. Shows system status, tests all URL generation methods, and includes live AJAX search testing.
- **Debug Mode for Redirects**: When searching for an order, the redirect is now intercepted and displayed on-screen instead of auto-redirecting. Shows the exact URL that would be used, with buttons to test it. This helps diagnose redirect issues.
- Enhanced console logging for redirect operations (shows redirect URL and order data in browser console).
- Additional debug logging in AJAX handler to track redirect URL generation.

### Fixed
- **HPOS Order URL Redirect**: Fixed order search redirect taking users to "All Posts" page instead of the order editor when HPOS (High-Performance Order Storage) is enabled. Now uses WooCommerce's `get_edit_order_url()` method which properly handles both HPOS and legacy storage modes.
- Added URL generation test diagnostic accessible via `/wp-admin/?kiss_test_url=1&order_id=12345` when debug mode is enabled.

## [1.1.1] - 2026-01-09

### Added
- **Diagnostic Endpoint**: When `KISS_WOO_FAST_SEARCH_DEBUG` is enabled, access `/wp-admin/?kiss_diag=1&order=B331580` to run comprehensive order search diagnostics including HPOS status, Sequential Order Numbers Pro integration, and direct database lookups.

### Changed
- **Auto-redirect on order search**: When user searches for an order number, the page now automatically redirects to the order editor instead of showing results.

### Fixed
- **HTML entities in order total**: Fixed `&#36;` displaying instead of `$` in order total display by properly decoding HTML entities.

## [1.1.0] - 2026-01-09

### Added
- **Order Number Search**: Search directly by order number (e.g., `12345`, `#12345`, `B349445`, `#B349445`).
- **SkyVerge Sequential Order Numbers Pro** integration via `wc_seq_order_number_pro()->find_order_by_order_number()`.
- **Debug Tracer System** (`KISS_Woo_Debug_Tracer`): Centralized observability with timing and context for all operations.
- **Search Cache** (`KISS_Woo_Search_Cache`): 5-minute transient caching for order lookups.
- **Order Resolver** (`KISS_Woo_Order_Resolver`): Centralized order-by-number lookups with single write path.
- **Order Formatter** (`KISS_Woo_Order_Formatter`): Consistent order-to-array conversion for API responses.
- **Debug Panel**: Admin UI under WooCommerce menu (when `KISS_WOO_FAST_SEARCH_DEBUG` is true) showing request traces, timing breakdowns, and system status.
- Console debug logging in JavaScript when debug mode is enabled.
- Custom prefix support via `kiss_woo_order_search_prefixes` filter (default: B, D).

### Changed
- AJAX handler now returns `orders` array, `should_redirect_to_order`, and `redirect_url` for order searches.
- Toolbar placeholder updated to "Search email, name, or order #…".
- Performance: Stop loading full usermeta (`all_with_meta`) during search results; fetch only core user fields and batch-load just `billing_first_name`, `billing_last_name`, and `billing_email`.
- Maintenance: Add a warning tripwire if `get_recent_orders_for_customer()` is called multiple times (helps catch accidental N+1 reintroduction).
- Performance: Batch “recent orders” fetch no longer relies on `wc_get_orders()` customer-array support; uses a single legacy SQL `IN (...)` lookup + one hydration call.

### Security
- Added nonce protection to benchmark page to follow WordPress security best practices.

### Fixed
- Order “View” links now open the order editor correctly (return raw edit URLs in JSON; avoid `&#038;` entity-encoded query strings).

## [1.0.2] - 2026-01-06

### Added
- Settings page under **WooCommerce → KISS Search Settings** with option to globally hide the floating search toolbar.
- Settings link in the plugins listing page for quick access.
- Global setting to hide the 2nd admin search toolbar for all users.

## [1.0.1] - 2025-XX-XX

### Changed
- Optimized `search_customers()` to avoid N+1 queries by batching user meta loads and fetching order counts + recent orders in aggregated queries.
- Added batch helpers for order counts (HPOS + legacy fallback) and recent orders per customer.
- Updated customer matching to prefer WooCommerce `wc_customer_lookup` (no HPOS required) to avoid wp_usermeta OR+LIKE scans, with a fallback to the previous query.

### Added
- Sticky admin toolbar search input that redirects to the KISS Search admin page with a `q` parameter.
- Auto-run search on the KISS Search page when opened with `?q=...`.
- Debug logging for customer searches (enabled by default; disable via `KISS_WOO_COS_DEBUG`).

### Fixed
- Floating toolbar bootstrap timing so it reliably appears when the plugin is active.

## [1.0.0] - 2025-XX-XX

### Added
- Initial release.
- Admin submenu under WooCommerce with simple search form for customers and guest orders.
- Capability and nonce checks on AJAX handler to prevent unauthorized access.
- Server-side validation ensuring search terms are trimmed and at least two characters long.
- Localized strings and AJAX configuration injected via `wp_localize_script`.
- Benchmark page (`WooCommerce → KISS Benchmark`) for search performance comparisons.

[Unreleased]: https://github.com/yourusername/kiss-woo-fast-search/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/yourusername/kiss-woo-fast-search/compare/v1.0.2...v1.1.0
[1.0.2]: https://github.com/yourusername/kiss-woo-fast-search/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/yourusername/kiss-woo-fast-search/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/yourusername/kiss-woo-fast-search/releases/tag/v1.0.0

