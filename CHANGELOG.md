# Changelog

All notable changes to this plugin will be documented in this file.

## Unreleased

### Changed
- Optimized `search_customers()` to avoid N+1 queries by batching user meta loads and fetching order counts + recent orders in aggregated queries.
- Added batch helpers for order counts (HPOS + legacy fallback) and recent orders per customer.

### Added
- Sticky admin toolbar search input that redirects to the KISS Search admin page with a `q` parameter.
- Auto-run search on the KISS Search page when opened with `?q=...`.

### Fixed
- Floating toolbar bootstrap timing so it reliably appears when the plugin is active.

