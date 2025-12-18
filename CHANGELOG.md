# Changelog

All notable changes to **KISS - Faster Customer & Order Search** will be documented in this file.

## [1.0.2] - 2025-12-18

### Added
- **Dashboard Widget**: New WordPress admin dashboard widget for quick customer/order search
  - Search input field on the WP admin home page
  - Submitting the search redirects to the full KISS search results page with query pre-filled
  - Auto-executes search when arriving from the dashboard widget
- Widget is enabled and visible by default for all users with `manage_woocommerce` capability
- "Go to Full Search" button in the widget for quick access to the main search page

### Changed
- Updated admin JavaScript to detect and auto-run searches from URL query parameter

## [1.0.1] - Initial tracked version

### Added
- Initial plugin release with customer and order search functionality
- Admin page under WooCommerce menu for searching customers by email or name
- AJAX-based search with XSS protection via `escapeHtml()`
- Performance benchmark page comparing KISS vs native WooCommerce queries
- HPOS (High-Performance Order Storage) support when available

