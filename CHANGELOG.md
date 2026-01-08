# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed
- Performance: Stop loading full usermeta (`all_with_meta`) during search results; fetch only core user fields and batch-load just `billing_first_name`, `billing_last_name`, and `billing_email`.
- Maintenance: Add a warning tripwire if `get_recent_orders_for_customer()` is called multiple times (helps catch accidental N+1 reintroduction).
- Performance: Batch “recent orders” fetch no longer relies on `wc_get_orders()` customer-array support; uses a single legacy SQL `IN (...)` lookup + one hydration call.
- **Debug Info Enhancement**: AJAX responses now include both order search debug info and customer search debug info when searching for order numbers that don't exist. Previously, customer search debug would overwrite order search debug, making it impossible to see why an order wasn't found.

### Security
- Added nonce protection to benchmark page to follow WordPress security best practices.

### Fixed
- Order “View” links now open the order editor correctly (return raw edit URLs in JSON; avoid `&#038;` entity-encoded query strings).
- Fixed debug info display for failed order searches: Now preserves order search trace data (including fast path and meta lookup attempts) even when falling back to customer search.

---

## [1.0.6] - 2026-01-08

### Added
- **Enhanced Error Reporting**: AJAX failures now show detailed error information including HTTP status, error message, and full response text.
- Console logging of all AJAX responses for easier debugging.

### Changed
- Improved error display with color-coded panels (red for AJAX failures, yellow for unsuccessful responses).
- Failed requests now show complete diagnostic information instead of generic "Request failed" message.

---

## [1.0.5] - 2026-01-08

### Added
- **On-screen Debug Output**: Added visual debug information display in search results to diagnose order search issues.
- Comprehensive debug tracking for both fast path (direct ID lookup) and meta fallback (sequential order numbers).
- **SkyVerge Plugin Integration**: Now uses `wc_sequential_order_numbers()->find_order_by_order_number()` helper function when available.

### Changed
- AJAX responses now include detailed debug information about order search attempts.
- Search class now tracks and exposes debug info via `$last_lookup_debug` property.
- Meta lookup now tries SkyVerge helper function first, then falls back to direct meta query for other plugins.

---

## [1.0.4] - 2026-01-08

### Added
- **Sequential Order Number Support**: Added meta-based fallback lookup for plugins like SkyVerge Sequential Order Numbers where order number ≠ order ID.
- Two-tier search strategy: Fast path (< 20ms) tries direct ID lookup first, then falls back to meta query (50-150ms) if needed.
- HPOS-compatible meta search (searches `wp_wc_orders_meta` or `wp_postmeta` depending on HPOS status).

### Changed
- Order search now uses two-tier lookup: direct ID first, then `_order_number` meta fallback.
- Updated docblocks to reflect new meta-based fallback capability.

### Fixed
- **Critical**: Order search now finds orders from sequential order number plugins (e.g., `#B331580` now works even if order ID ≠ 331580).

---

## [1.0.3] - 2026-01-08

### Added
- **Direct Order ID Search**: Search for orders by exact numeric ID in the toolbar (e.g., `12345`, `#12345`, `B349445`, `D349445`).
- Fast path order lookup using direct `wc_get_order($id)` - guaranteed < 20ms performance for numeric ID lookups.
- Auto-redirect to order edit page when searching for exact order ID match.
- B/D prefix support with validation - parses prefix, extracts numeric ID, looks up order, verifies `get_order_number()` matches input.
- New `kiss_woo_order_search_prefixes` filter for developers to customize allowed prefixes.
- "Matching Orders" section in search results when order is found but no redirect.
- Unit tests for order term parsing (`tests/unit/test-order-term-parsing.php`).
- Benchmark tests for order ID search performance (WooCommerce stock vs KISS fast path).
- Tests README with instructions for running tests and expected performance metrics.

**Important:** This feature performs **exact numeric ID lookup**, not arbitrary order-number string reverse lookup. It works when:
- Order number equals order ID (default WooCommerce behavior)
- Order number is formatted as `B{ID}` or `D{ID}` (common display formatting)
- Sites using sequential order plugins with non-numeric order numbers will see no match unless the custom order number happens to match the underlying order ID.

### Changed
- Updated toolbar placeholder text to "Search email, name, or order #…" to indicate order search capability.
- Updated i18n string `no_results` to mention "customers or orders".
- AJAX response now includes `orders` array and `should_redirect_to_order` boolean.

### Fixed
- **Critical (P0)**: Order number validation now always checks `get_order_number()` matches input to prevent wrong order redirect when using sequential order number plugins (where order number ≠ order ID).
- **Performance (P1)**: AJAX handler now returns immediately on exact order match (< 20ms) instead of running customer/guest searches unnecessarily.
- **Security (P2)**: Added `rel="noopener noreferrer"` to all `target="_blank"` links to prevent tabnabbing attacks.
- **Tests (P0)**: Fixed unit test expectations for B-prefix parsing (B349 is valid, not invalid).
- **Benchmark (P2)**: Updated benchmark to use numeric order ID for KISS search (not order number string) and relabeled "cached" as "warm" (same-request object reuse, not plugin-level caching).
- **Documentation (P3)**: Clarified that feature performs exact numeric ID lookup, not arbitrary order-number string reverse lookup. Updated CHANGELOG, README, and inline comments to reflect actual implementation scope.

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

[Unreleased]: https://github.com/yourusername/kiss-woo-fast-search/compare/v1.0.6...HEAD
[1.0.6]: https://github.com/yourusername/kiss-woo-fast-search/compare/v1.0.5...v1.0.6
[1.0.5]: https://github.com/yourusername/kiss-woo-fast-search/compare/v1.0.4...v1.0.5
[1.0.4]: https://github.com/yourusername/kiss-woo-fast-search/compare/v1.0.3...v1.0.4
[1.0.3]: https://github.com/yourusername/kiss-woo-fast-search/compare/v1.0.2...v1.0.3
[1.0.2]: https://github.com/yourusername/kiss-woo-fast-search/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/yourusername/kiss-woo-fast-search/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/yourusername/kiss-woo-fast-search/releases/tag/v1.0.0

