# Changelog

All notable changes to this plugin will be documented in this file.

## Unreleased

- Performance: Stop loading full usermeta (`all_with_meta`) during search results; fetch only core user fields and batch-load just `billing_first_name`, `billing_last_name`, and `billing_email`.

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

