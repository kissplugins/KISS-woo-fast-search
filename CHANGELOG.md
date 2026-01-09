# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

