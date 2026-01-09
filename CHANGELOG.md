# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.1] - 2026-01-09

### Added
- **Diagnostic Endpoint**: When `KISS_WOO_FAST_SEARCH_DEBUG` is enabled, access `/wp-admin/?kiss_diag=1&order=B331580` to run comprehensive order search diagnostics including HPOS status, Sequential Order Numbers Pro integration, and direct database lookups.

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

