# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

---

## [1.2.18] - 2026-03-06

### Fixed
- **CRITICAL: `current_user_can()` called too early at `plugins_loaded`** — Toolbar bootstrap (`toolbar.php:202`) called `current_user_can('manage_woocommerce')` at file-include time during `plugins_loaded`, before WordPress initializes the user session (which happens at `init`). This could cause the toolbar to silently fail to load for legitimate users.
  - **Fix**: Removed capability check from bootstrap; only `is_admin()` is checked at include time. All three callback methods (`enqueue_assets`, `render_toolbar`, `add_toolbar_body_class`) already gate on `current_user_can('manage_woocommerce')` and fire on later hooks where the user session is available.

- **HIGH: Unscoped `!important` on core WP selectors** — `#wpcontent` and `#adminmenuwrap` margin-top rules used bare global selectors with `!important`, risking conflicts with other plugins/themes.
  - **Fix**: Scoped rules under `body.kiss-toolbar-active` — a new server-side body class added via `admin_body_class` filter. This provides a kill-switch: if the toolbar class isn't instantiated or the user lacks capability, the body class is absent and layout-push rules don't apply.
  - **Files Modified**: `toolbar.php` (new `add_toolbar_body_class()` method + filter hook), `admin/css/kiss-woo-toolbar.css`

- **MEDIUM: Potential double-offset on Gutenberg block editor pages** — Both `kiss-woo-toolbar.css` (pushes `#wpcontent` down) and `kiss-woo-toolbar-editor.css` (pushes `.interface-interface-skeleton` down) loaded on editor pages. If the editor layout ever falls back to flow positioning, the toolbar height would be added twice.
  - **Fix**: `kiss-woo-toolbar-editor.css` now resets `body.kiss-toolbar-active #wpcontent` and `body.kiss-toolbar-active #adminmenuwrap` margin to `0` on block editor pages (this file only loads via `enqueue_block_editor_assets`). The skeleton offset handles editor layout exclusively.

---

## [1.2.17] - 2026-03-06

### Fixed
- **HIGH: Toolbar z-index and content push-down**: Rewrote toolbar CSS to properly stack below WP admin bar and push admin content down correctly
  - **Issue**: Toolbar used `z-index: 9997` (too low, got covered by other elements) and relied on a JS-added body class (`.floating-toolbar-active`) for content push-down, which didn't push `#adminmenuwrap` (left sidebar menu)
  - **Impact**: Toolbar could be covered by other UI elements; admin content and left sidebar overlapped with toolbar
  - **Solution**: Adopted stacking approach from Neochrome Toolbar reference implementation:
    - Changed `z-index` from `9997` to `99998` (just below WP admin bar's `99999`)
    - Added CSS custom properties (`--kiss-toolbar-height`, `--kiss-wp-admin-bar-height`) for maintainable sizing
    - Changed content push to direct selectors with `!important`: `#wpcontent` and `#adminmenuwrap` both get `margin-top`
    - Removed dependency on `.floating-toolbar-active` body class for layout
    - Added `body.is-fullscreen-mode` support for Gutenberg fullscreen
  - **Files Modified**:
    - `admin/css/kiss-woo-toolbar.css` — Rewrote positioning, z-index, and content push-down rules
    - `admin/css/kiss-woo-toolbar-editor.css` — **NEW**: Gutenberg block editor layout overrides
    - `toolbar.php` — Added `enqueue_block_editor_assets` hook, removed block editor skip logic

### Technical Notes
- **Z-index Stack**: WP admin bar = `99999`, KISS toolbar = `99998`, media modal = `160000`
- **CSS Variables**: `--kiss-toolbar-height: 36px` (desktop), `46px` (mobile ≤782px)
- **Gutenberg Support**: Toolbar now renders in block editor; `.interface-interface-skeleton` offset handles editor layout
- **Backward Compat**: `floating-toolbar-active` body class still added by JS (harmless) but no longer required for layout

---

## [1.2.16] - 2026-03-04

### Removed
- **Deleted developer debug scratch files** from repository:
  - `debug-coupon-search.php` — contained hardcoded local path (`/Users/noelsaw/...`) and test coupon code
  - `test-coupon-upsert.php` — contained hardcoded coupon ID `1323821`
- Added `debug-*.php` and `test-*.php` patterns to `.gitignore` to prevent future commits

### Fixed
- **Cleared hardcoded test coupon code** in `admin/coupon-diagnostic.php` — replaced pre-filled `r1m8jj1xt2m1m` value with empty default in the "Test Single Coupon" form field

---

## [1.2.15] - 2026-03-04

### Fixed
- **Performance: Eliminated N+1 query in coupon fallback search** (Finding #3 from audit)
  - Replaced per-coupon `new WC_Coupon()` loop with 2 batch SQL queries (posts + postmeta)
  - Now reuses existing `KISS_Woo_Coupon_Formatter::format_from_row()` instead of `format_from_coupon()`
  - Reduces fallback search from N+2 queries to exactly 2 queries regardless of result count
  - **File Modified**: `includes/class-kiss-woo-coupon-search.php` — `fallback_search()` method rewritten

---

## [1.2.14] - 2026-03-04

### Changed
- **Merged feature/add-coupon-search into development**: Integrated WP CLI table lookup functionality and coupon search improvements
  - Resolved merge conflicts between development (newer, Feb 7) and feature branch (Jan 28)
  - Consolidated debug logging to use `KISS_Woo_Debug_Tracer::log()` throughout (removed conditional error_log calls)
  - Maintained all wholesale search features and security fixes from development branch
  - Preserved coupon lookup builder and admin UI features from feature branch
  - Version bumped to 1.2.14 to reflect merge

### Technical Details
- Merged branches: `feature/add-coupon-search` → `development`
- Conflicts resolved in:
  - `CHANGELOG.md` - Combined changelog entries from both branches
  - `includes/class-kiss-woo-search.php` - Unified debug logging approach
  - `kiss-woo-fast-order-search.php` - Updated version to 1.2.14
- All debug logging now uses centralized `KISS_Woo_Debug_Tracer` (no more direct error_log calls)
- Maintained backward compatibility with both branches' features

---

## [1.2.13] - 2026-02-04

### Fixed
- **LOW: Benchmark page default email contains PII**: Changed default benchmark query from personal email to team email
  - **Issue**: Default query hardcoded as `$query = 'vishal@neochro.me';` (personal PII)
  - **Impact**: Personal email exposed in production code; not production-ready
  - **Solution**: Changed default to `devops@neochro.me` (team/role-based email)
  - **Files Modified**:
    - `admin/class-kiss-woo-admin-page.php` - Line 189 (changed default query)
  - **Result**: No personal PII in production code; benchmark page uses team email by default

### Technical Notes
- **Benchmark Page**: Located at WooCommerce → KISS Benchmark
- **Default Query**: Now uses `devops@neochro.me` instead of personal email
- **User Override**: Users can still test with any email via query parameter `?q=email@example.com`

---

## [1.2.12] - 2026-02-03

### Fixed
- **HIGH: Wrong debug constant in list handlers**: Fixed `get_debug_data()` using wrong constant, preventing debug data from being attached to wholesale/recent order list responses
  - **Issue**: `handle_search()` uses `KISS_WOO_FAST_SEARCH_DEBUG` but `get_debug_data()` checked `KISS_WOO_DEBUG` (non-existent constant)
  - **Impact**: Wholesale and recent order list responses never included debug traces, even when debug mode was enabled
  - **Solution**: Changed `get_debug_data()` to use `KISS_WOO_FAST_SEARCH_DEBUG` (consistent with rest of plugin)
  - **Files Modified**:
    - `includes/class-kiss-woo-ajax-handler.php` - Line 393 (changed constant check)
  - **Affected Endpoints**: `handle_list_wholesale_orders()`, `handle_list_recent_orders()`
  - **Result**: Debug data now correctly attached to all AJAX responses when debug mode enabled

- **MEDIUM: Verbose error_log() in production**: Gated 10 unconditional `error_log()` calls behind debug flag to prevent log noise in production
  - **Issue**: Multiple `error_log()` calls in `class-kiss-woo-search.php` ran regardless of debug mode
  - **Impact**: Noisy production logs with SQL queries, timing data, memory usage; potential information disclosure
  - **Solution**: Wrapped all verbose logging in `if ( defined( 'KISS_WOO_FAST_SEARCH_DEBUG' ) && KISS_WOO_FAST_SEARCH_DEBUG )` checks
  - **Files Modified**:
    - `includes/class-kiss-woo-search.php` - Lines 262, 302, 309, 447, 454, 470, 1067, 1126, 1162, 1173
  - **Logs Gated**:
    - Lookup table not found warnings
    - Name-pair SQL queries and timing
    - Order count queries (HPOS/legacy) start/done
    - Recent orders queries start/done
    - Order hydration start/done
  - **Result**: Clean production logs; verbose debugging only when explicitly enabled

### Technical Notes
- **Debug Constant**: Plugin uses `KISS_WOO_FAST_SEARCH_DEBUG` throughout (not `KISS_WOO_DEBUG`)
- **Enable Debug Mode**: Add `define( 'KISS_WOO_FAST_SEARCH_DEBUG', true );` to `wp-config.php`
- **Debug Data Structure**: `{ traces, memory_peak_mb, php_version, wc_version }`
- **Performance**: No performance impact - debug checks are simple constant lookups

---

## [1.2.11] - 2026-02-03

### Fixed
- **CRITICAL: HPOS support missing in recent orders**: Fixed `get_recent_orders_for_customers()` ignoring HPOS-stored orders
  - **Issue**: Method only queried `wp_posts` and `wp_postmeta` (legacy shop_order storage), ignoring `wc_orders` table
  - **Problem**: When WooCommerce HPOS (High-Performance Order Storage) is enabled, orders are stored in `wc_orders` table, not `wp_posts`
  - **Impact**: On HPOS-enabled sites, customer search results showed 0 recent orders for every customer, even when they had orders
  - **Solution**:
    - Added HPOS detection using `KISS_Woo_Utils::is_hpos_enabled()`
    - When HPOS enabled: Query `wc_orders` table by `customer_id` with `date_created_gmt` ordering
    - When HPOS disabled: Use legacy `wp_posts` + `wp_postmeta` query (backward compatible)
    - Order hydration already supported HPOS via `get_order_data_via_sql()` (no changes needed)
  - **Files Modified**:
    - `includes/class-kiss-woo-search.php` - Lines 1035-1113 (added HPOS detection and dual query paths)
  - **Performance**: No performance impact - same query structure, just different tables
  - **Observability**: Added HPOS/legacy indicator to debug logs

### Technical Notes
- **HPOS Query**: `SELECT id, customer_id FROM wc_orders WHERE type='shop_order' AND status IN (...) AND customer_id IN (...) ORDER BY date_created_gmt DESC`
- **Legacy Query**: `SELECT p.ID, pm.meta_value FROM wp_posts p JOIN wp_postmeta pm WHERE p.post_type='shop_order' AND p.post_status IN (...) AND pm.meta_value IN (...) ORDER BY p.post_date_gmt DESC`
- **Backward Compatibility**: Legacy sites (HPOS disabled) continue to use existing query path
- **Related Methods**: `get_order_data_via_sql()` already had HPOS support (lines 676-695), so order hydration worked correctly

---

## [1.2.10] - 2026-02-03

### Fixed
- **CRITICAL: Wholesale filter contract mismatch**: Fixed wholesale filter returning empty results due to data structure mismatch
  - **Issue**: `search_customers()` returned flat array but `KISS_Woo_Wholesale_Filter::apply()` expected structured hash with 'customers', 'guest_orders', 'orders' keys
  - **Problem**: Filter checked `isset($results['customers'])` which was false for flat array, causing it to return empty results
  - **Impact**: Wholesale filter feature was completely broken - no customers or orders shown when "Wholesale only" was enabled
  - **Solution**:
    - Modified `search_customers()` to wrap results in structured hash when filters are present
    - Updated AJAX handler to handle both flat array (no filters) and structured hash (with filters)
    - Updated interface documentation to clarify the filter contract
  - **Files Modified**:
    - `includes/class-kiss-woo-search.php` - Lines 174-183 (wrap results for filters)
    - `includes/class-kiss-woo-ajax-handler.php` - Lines 163-187 (handle both structures)
    - `includes/interface-kiss-woo-order-filter.php` - Lines 16-46 (document contract)
  - **SOLID Principle**: Fixed Interface Segregation violation - filter interface now has clear, documented contract
  - **Backward Compatibility**: No breaking changes - flat array still returned when no filters applied

### Technical Notes
- **Filter Contract**: Filters now receive and return structured hash: `['customers' => [], 'guest_orders' => [], 'orders' => []]`
- **Performance**: No performance impact - structure wrapping only happens when filters are present
- **Observability**: Added debug logging to show structure type ('flat' vs 'hash') in AJAX handler

---

## [1.2.9] - 2026-02-03

### Fixed
- **Security: Removed HTML escaping from JSON URL fields**: Fixed double-escaping issue in user edit URLs
  - **Issue**: User edit URLs in JSON responses were being escaped with `esc_url()`, causing `&` to become `&#038;`
  - **Problem**: JavaScript redirects and URL handling would break due to HTML entities in URLs
  - **Impact**: Potential issues with user profile links when used in JavaScript contexts
  - **Solution**: Removed `esc_url()` from `edit_url` field in JSON response (line 168 in `class-kiss-woo-search.php`)
  - **Security**: URLs are already safe from `get_edit_user_link()` which uses `admin_url()`, and JavaScript properly escapes URLs when rendering to HTML
  - **Pattern**: Follows same fix previously applied to order URLs in `class-kiss-woo-order-formatter.php`
  - **Verification**: WPCC security scan now passes with 0 warnings (previously had 1 warning)
  - **Reference**: See `PROJECT/3-COMPLETED/DOUBLE-ESCAPE-BUG-FIX.md` for detailed explanation

### Security
- **WPCC Security Audit**: Full codebase scan completed with AI-DDTK WordPress Code Check tool
  - **Results**: 0 critical errors, 0 warnings, all 40 security and performance checks passed
  - **Files Analyzed**: 1,215 files (134,848 lines of code including vendor dependencies)
  - **Checks Passed**:
    - ✅ No unbounded database queries
    - ✅ No direct superglobal access without sanitization
    - ✅ No missing nonce verification
    - ✅ No SQL injection vulnerabilities
    - ✅ No N+1 query patterns
    - ✅ No missing transient caching issues
    - ✅ All AJAX handlers have proper nonce validation
    - ✅ All admin functions have capability checks
  - **Report**: `wpcc-security-audit-report.html` (39.1KB)

---

## [1.2.8] - 2026-02-02

### Fixed
- **Scope toggle locked to "Users/Orders" in listing mode**: Fixed toggle defaulting to "Coupons" when viewing order lists
  - **Issue**: When viewing "Wholesale Orders" or "Recent Orders", the scope toggle would sometimes default to "Coupons" instead of "Users/Orders"
  - **Problem**: JavaScript was setting the scope but not explicitly unchecking the other radio button, and toggle was still interactive
  - **Impact**: Users saw "Coupons" selected when viewing order lists (confusing and incorrect)
  - **Solution**:
    - Force scope to 'users' in listing mode
    - Explicitly uncheck the "Coupons" radio button
    - Disable both radio buttons (make read-only) in listing mode
    - Visual feedback: Toggle appears dimmed (opacity: 0.5) and non-interactive (pointer-events: none)
  - **Affected**:
    - `admin/js/kiss-woo-toolbar.js` - Lines 76-108 (force scope, disable inputs)
    - `admin/css/kiss-woo-toolbar.css` - Lines 164-169 (visual disabled state)
    - `toolbar.php` - Lines 120-127 (detect listing mode, add CSS class)

### Technical Notes
- **Design Philosophy**: This is a perfect example of **DRY with context-aware flexibility**
  - Same component (toolbar) adapts to different contexts (search vs. listing)
  - Toggle is visible but disabled in listing mode (clear visual feedback)
  - No over-engineering (simple CSS class + disabled state)
  - User-friendly (toggle shows current context but prevents accidental changes)

---

## [1.2.7] - 2026-02-02

### Fixed
- **Search scope defaulting to coupons after viewing order lists**: Fixed toolbar scope restoration logic
  - **Issue**: When users clicked "Wholesale Orders" or "Recent Orders", the search scope would default to "Coupons" instead of "Users/Orders"
  - **Problem**: Toolbar was restoring saved scope from localStorage without considering listing mode context
  - **Impact**: Users had to manually switch back to "Users/Orders" scope after viewing order lists (confusing UX)
  - **Fix**: Added logic to force scope to 'users' when `list_wholesale` or `list_recent` parameters are present
  - **Affected**: Lines 76-94 in `admin/js/kiss-woo-toolbar.js`
  - **Credit**: Bug reported by user during testing

### Changed
- **Recent Orders now shows "Most Recent 50 Orders" instead of "Last Hour"**: Changed from time-based to limit-based filtering
  - **Reason**: User's test site uses old snapshot data with no orders in the last hour
  - **Old behavior**: Listed orders from last hour using `date_from` filter (could return 0 results on old data)
  - **New behavior**: Lists exactly 50 most recent orders (always returns results if any orders exist)
  - **Implementation**: Removed date filter, set `per_page = 50` and `page = 1` (single page, no pagination)
  - **Impact**: More reliable for testing and development sites with old data
  - **Affected**:
    - `includes/class-kiss-woo-ajax-handler.php` - Lines 305-371
    - `admin/kiss-woo-admin.js` - Line 419 (title changed)
    - `admin/class-kiss-woo-admin-page.php` - Line 130 (i18n string added)

---

## [1.2.6] - 2026-02-02

### Added
- **"Fast Search..." Dropdown Menu**: Converted single wholesale button to dropdown menu with multiple quick-access options
  - **Menu Items**:
    - "Recent Orders" - Lists orders from the last hour (new feature)
    - "Wholesale Orders Only" - Lists all wholesale orders (existing feature)
  - **UI/UX Improvements**:
    - Dropdown positioned on far left of toolbar (separated from search controls)
    - Accessible ARIA attributes (aria-haspopup, aria-expanded, aria-hidden)
    - Click-outside-to-close functionality
    - Keyboard navigation support (Enter/Space to toggle)
    - Smooth transitions and hover states
    - Mobile responsive styling
  - **Impact**: Provides quick access to common order listing tasks without cluttering the toolbar

- **Recent Orders Listing Feature**: New "Recent Orders" functionality
  - Lists orders created in the last hour (configurable date range)
  - Uses same centralized `KISS_Woo_Order_Query` helper (DRY principle)
  - Same performance benefits as wholesale listing (25-200x faster than WooCommerce native)
  - Pagination support with same UI as wholesale orders
  - HPOS and legacy storage mode support
  - **Impact**: Support teams can quickly view recent orders for troubleshooting and customer service

- **Date Filtering Support in Order Query Helper**: Enhanced `KISS_Woo_Order_Query` with date range filtering
  - Added `date_from` and `date_to` parameters to `query_orders()` method
  - Implemented in both HPOS query (`o.date_created_gmt >= %s`)
  - Implemented in legacy query (`p.post_date_gmt >= %s`)
  - **Extensibility**: Can be used for custom date range reports, daily/weekly/monthly order lists, etc.

### Changed
- **DRY Refactoring of Admin JavaScript**: Converted wholesale-specific code to generic order listing system
  - Refactored `loadWholesaleOrders()` to generic `loadOrderList(listType, page, perPage)` function
  - Added configuration object for different list types (wholesale, recent)
  - Each type has: action, loadingMsg, title, noResultsMsg, countLabel
  - Updated `renderPagination()` to accept `listType` parameter
  - Added delegated event handler for pagination buttons (supports dynamic content)
  - **Impact**: Easy to add new list types in the future (just add to config object)

- **Toolbar Button Repositioned**: Moved wholesale button (now dropdown) to far left side of toolbar
  - Created separate `.floating-search-toolbar__section--left` section
  - Added `margin-right: auto` to push button to far left
  - Separated from search controls for better visual clarity
  - **Impact**: Reduces confusion between search and listing functions

### Technical Details
- **Files Modified**:
  - `toolbar.php` - Dropdown menu HTML structure
  - `admin/css/kiss-woo-toolbar.css` - Dropdown styling
  - `admin/js/kiss-woo-toolbar.js` - Dropdown interaction handlers
  - `includes/class-kiss-woo-ajax-handler.php` - Added `handle_list_recent_orders()` method
  - `includes/class-kiss-woo-order-query.php` - Added date filtering support
  - `admin/class-kiss-woo-admin-page.php` - Added `list_recent` parameter detection
  - `admin/kiss-woo-admin.js` - DRY refactoring for order listing

- **New AJAX Endpoint**: `kiss_woo_list_recent_orders`
  - Action: `wp_ajax_kiss_woo_list_recent_orders`
  - Handler: `KISS_Woo_Ajax_Handler::handle_list_recent_orders()`
  - Parameters: `page`, `per_page`, `nonce`
  - Response: Same structure as wholesale listing

- **Date Calculation**: Recent orders use `gmdate('Y-m-d H:i:s', strtotime('-1 hour'))` for last hour

---

## [1.2.5] - 2026-02-02

### Fixed
- **CRITICAL: Wholesale listing broken in legacy mode (non-HPOS)**: Fixed SQL bug in `KISS_Woo_Order_Query::get_wholesale_meta_condition()`
  - **Issue**: Method used `m.order_id` for both HPOS and legacy modes
  - **Problem**: Legacy mode uses `wp_postmeta` table which has `post_id` column, not `order_id`
  - **Impact**: Wholesale listing returned 0 results for sites not using HPOS (100% broken)
  - **Fix**: Auto-detect meta table and use correct foreign key column (`post_id` for legacy, `order_id` for HPOS)
  - **Affected**: Line 262 in `includes/class-kiss-woo-order-query.php`
  - **Credit**: Bug discovered by user testing on legacy dev site

- **CRITICAL: Legacy query missing customer data**: Fixed `KISS_Woo_Order_Query::build_legacy_query()` to include order details
  - **Issue**: Legacy query only selected `id`, `status`, `date_created_gmt`
  - **Problem**: Formatter expects `total_amount`, `currency`, `billing_email`, `first_name`, `last_name`
  - **Impact**: Order cards showed empty customer names, totals, and emails in legacy mode
  - **Fix**: Added LEFT JOINs with `wp_postmeta` to retrieve order meta fields using CASE/MAX pattern
  - **Meta keys**: `_order_total`, `_order_currency`, `_billing_email`, `_billing_first_name`, `_billing_last_name`
  - **Affected**: Lines 224-248 in `includes/class-kiss-woo-order-query.php`
  - **Credit**: Bug discovered by user testing on legacy dev site

---

## [1.2.5] - 2026-02-02

### Added
- **Wholesale Orders Listing Feature**: New "List All Wholesale Orders" functionality
  - Lists ALL wholesale orders without requiring a search term (replaces WooCommerce → Orders → Wholesale filter)
  - **25-200x faster** than WooCommerce native order listing (direct SQL queries bypass WooCommerce hooks)
  - Pagination support (100 orders per page default, configurable up to 500)
  - Created centralized `KISS_Woo_Order_Query` helper class (~330 lines) - single source of truth for order listing
  - **Reusable architecture**: Can list wholesale, retail, B2B, or any custom order type
  - HPOS and legacy storage mode support (auto-detects via `KISS_Woo_Utils::is_hpos_enabled()`)
  - Comprehensive debug logging with performance tracking
  - **Impact**: Wholesale store owners can view all wholesale orders in 50-200ms instead of 5-10 seconds
  - **Extensibility**: Helper can be used for retail orders, B2B orders, order reports, exports, and dashboards

- **Self-Test UI for Wholesale Listing**: Comprehensive regression testing
  - Added wholesale tests section to existing self-test page
  - Tests: Wholesale detection, pagination (page 1, 2, invalid), performance (< 500ms threshold), empty results
  - Performance regression detection with baseline comparison
  - Detailed test results with JSON debug output
  - Accessible from WooCommerce → KISS Self-Test

### Changed
- **Toolbar wholesale button behavior**: Now redirects to admin page with `list_wholesale=1` parameter (no search term required)
- **Admin page**: Added `list_wholesale` parameter detection and auto-triggers wholesale listing on page load
- **Admin JavaScript**: Added wholesale listing handler with pagination support (~180 lines)
  - `loadWholesaleOrders(page, perPage)` - AJAX call to list wholesale orders
  - `renderPagination(currentPage, totalPages, perPage)` - Pagination controls with ellipsis for large page counts
  - Auto-scroll to results on page change
- **Admin CSS**: Added wholesale listing and pagination styles (~130 lines)
  - Order card layout with hover effects
  - Status badges with color coding (completed, processing, pending, cancelled, etc.)
  - Responsive pagination controls

### Technical Details
- **Files Added**: 1 new file
  - `includes/class-kiss-woo-order-query.php` (~330 lines) - Centralized order query helper
- **Files Modified**: 6 files (~400 lines added)
  - `includes/class-kiss-woo-ajax-handler.php` (+87 lines) - New `handle_list_wholesale_orders()` endpoint
  - `admin/js/kiss-woo-toolbar.js` (simplified wholesale button, removed search term requirement)
  - `admin/class-kiss-woo-admin-page.php` (+4 lines) - Added `list_wholesale` parameter and i18n strings
  - `admin/kiss-woo-admin.js` (+181 lines) - Wholesale listing and pagination handlers
  - `admin/css/kiss-woo-admin.css` (+130 lines) - Wholesale listing and pagination styles
  - `admin/class-kiss-woo-self-test.php` (+188 lines) - Wholesale listing self-tests
  - `kiss-woo-fast-order-search.php` (+1 line) - Registered new helper class
- **Total new production code**: ~530 lines (excluding tests)
- **SOLID Principles Applied**:
  - **Single Responsibility**: Each class has one job (query, format, filter, debug)
  - **Open/Closed**: Extend with new order types without modifying existing code
  - **Liskov Substitution**: All query types return same structure
  - **Interface Segregation**: Small, focused methods
  - **Dependency Inversion**: Depends on abstractions (existing helpers)
- **Performance**: Direct SQL queries with LIMIT/OFFSET for pagination, no WooCommerce hooks
- **Observability**: Comprehensive logging with `KISS_Woo_Debug_Tracer`, performance metrics in responses

### Architecture Highlights
- **Centralized Helper**: `KISS_Woo_Order_Query` is single source of truth for order listing
- **Reuses Existing Helpers**:
  - `KISS_Woo_Utils::is_hpos_enabled()` - HPOS detection
  - `KISS_Woo_Order_Formatter::format_from_raw()` - Order formatting
  - `KISS_Woo_Debug_Tracer::log()` - Centralized logging
  - Wholesale meta keys from `KISS_Woo_Wholesale_Filter`
- **Future-Proof**: Can easily add retail listing, B2B listing, or custom order type listing with 1 line of code

---

## [1.2.4] - 2026-02-02

### Added
- **Wholesale Orders Search Filter**: New "Search Wholesale Orders Only" button in sticky toolbar
  - Filters search results to show only wholesale orders (20-40x faster than WooCommerce native search)
  - Follows SOLID principles: Open/Closed, Single Responsibility, Dependency Inversion
  - Created `KISS_Woo_Order_Filter` interface for pluggable filter implementations
  - Created `KISS_Woo_Wholesale_Filter` class with dual detection logic:
    - Checks user roles: `wholesale_customer`, `wholesale_lead`, `wwpp_wholesale_customer`, `wws_wholesale_customer`
    - Checks order meta: `_wwpp_order_type`, `_wholesale_order`, `_is_wholesale_order`, `_wwp_wholesale_order`
  - Extended `KISS_Woo_Search::search_customers()` with optional `$filters` parameter (backward compatible)
  - Updated AJAX handler to detect `wholesale_only` POST parameter and apply filters
  - Added wholesale button to toolbar with click handler that redirects to admin page with `wholesale_only=1`
  - Admin page auto-searches when `q` parameter is present
  - Includes comprehensive debug tracing for filter operations
  - **Impact**: Wholesale store owners can now search wholesale orders 20-40x faster than WooCommerce native search
  - **Extensibility**: Filter architecture allows easy addition of retail_only, b2b_only, or custom filters in future

### Changed
- **Increased search result limits from 20 to 40**: Better coverage for large stores with hundreds of thousands of orders
  - Customer search limit: 20 → 40 (line 102 in `class-kiss-woo-search.php`)
  - Customer lookup limit: 20 → 40 (line 230 default parameter)
  - Guest orders limit: 20 → 40 (line 1584)
  - **New maximum results**: ~440 items (40 customers × 10 orders + 40 guest orders)
- Updated version number to 1.2.4 in main plugin file
- Extended `KISS_Woo_Search::search_customers()` signature to accept optional filters array
- Updated admin page to pass `wholesale_only` and `initial_search` parameters to JavaScript
- Updated admin JavaScript to include `wholesale_only` in AJAX requests

### Fixed
- **Wholesale button not responding**: Fixed click handler in toolbar JavaScript
  - Added `e.preventDefault()` to prevent default button behavior
  - Added user-friendly alert messages when search term is missing or too short
  - Added error logging when `searchUrl` config is missing
  - Added console warning when wholesale button is not found in DOM
  - Added visual cursor pointer feedback on hover

### Technical Details
- **Files Added**: 2 new files (~95 lines total)
  - `includes/interface-kiss-woo-order-filter.php` - Filter interface (15 lines)
  - `includes/filters/class-kiss-woo-wholesale-filter.php` - Wholesale filter implementation (80 lines)
- **Files Modified**: 6 files (~135 lines changed)
  - `includes/class-kiss-woo-search.php` - Added filter support (+40 lines)
  - `includes/class-kiss-woo-ajax-handler.php` - Added filter detection (+30 lines)
  - `admin/class-kiss-woo-admin-page.php` - Added URL parameter handling (+10 lines)
  - `admin/kiss-woo-admin.js` - Added wholesale_only to AJAX (+5 lines)
  - `toolbar.php` - Added wholesale button (+8 lines)
  - `admin/js/kiss-woo-toolbar.js` - Added wholesale button handler (+42 lines)
- **Total New Production Code**: ~230 lines

---

## [1.2.8] - 2026-01-28

### Fixed
- **Fixed Unconditional error_log() Calls** (Finding #1 from Audit)
  - **Problem**: 3 error_log() calls ran unconditionally in production, logging user counts and memory metrics
  - **Impact**: Unnecessary log noise in production, violates WordPress best practices for respecting WP_DEBUG
  - **Solution**: Replaced with `KISS_Woo_Debug_Tracer::log()` which automatically respects debug flags
  - **Locations Fixed**:
    - Line 1022: `get_recent_orders_for_customers START` → `get_recent_orders_start`
    - Line 1060: `get_recent_orders SQL done` → `get_recent_orders_sql_done`
    - Line 1094: `order hydration START` → `order_hydration_start`
  - **Benefits**:
    - ✅ **Respects WP_DEBUG** - Only logs when debug mode enabled
    - ✅ **Centralized logging** - Uses existing debug tracer infrastructure
    - ✅ **PII protection** - Debug tracer automatically redacts sensitive data
    - ✅ **Better observability** - Structured logging with component/action/context
    - ✅ **No functional changes** - Debug output still available when WP_DEBUG=true

### Changed
- **Improved Debug Logging Pattern**: Replaced direct error_log() with debug tracer
  - Old: `error_log('[KISS_WOO_COS] message - user_ids: ' . implode(',', $user_ids) . ' | memory: ' . $memory . 'MB')`
  - New: `KISS_Woo_Debug_Tracer::log('Search', 'action_name', ['user_count' => count($user_ids), 'memory_mb' => $memory])`
  - Logs user counts instead of actual user IDs (better privacy practice)
  - Structured context array instead of concatenated strings

### Technical Details
- Modified files:
  - `includes/class-kiss-woo-search.php` - Replaced 3 unconditional error_log() calls
- All 38 PHPUnit tests passing
- Debug output verified working when `KISS_WOO_FAST_SEARCH_DEBUG` constant enabled

---

## [1.2.7] - 2026-01-28

### Fixed
- **CRITICAL: Fixed Infix LIKE Scans Causing Full Table Scans** (Finding #2 from Audit)
  - **Problem**: 2 out of 3 OR conditions used infix search (`%term%`) instead of prefix search (`term%`)
  - **Impact**: At 360k+ coupons, every search scanned entire table causing multi-second query times and timeouts
  - **Root Cause**: Line 98 in `class-kiss-woo-coupon-search.php` used `$term_like` (infix) instead of `$term_prefix` (prefix)
  - **Solution**: Implemented FULLTEXT index with BOOLEAN MODE search
    - Added `FULLTEXT KEY idx_search_fulltext (code_normalized, title, description_normalized)` to schema
    - Replaced OR conditions with `MATCH(...) AGAINST('term*' IN BOOLEAN MODE)`
    - Wildcard `*` enables prefix matching: "summer*" matches "summer", "summer2024", etc.
  - **Benefits**:
    - ✅ **No UX impact** - Maintains current search behavior (finds "SUMMER" in "BIGSUMMER2024")
    - ✅ **Best performance** - FULLTEXT optimized for text search, faster than LIKE even with prefix
    - ✅ **Scalable** - Performance stays consistent as coupon count grows
    - ✅ **MySQL 5.6+ compatible** - Available on 99%+ of WordPress sites
  - **Database Changes**:
    - Schema version bumped from 1.0 to 1.1
    - Existing sites will auto-upgrade via `dbDelta()` on next admin page load
    - FULLTEXT index created automatically during upgrade

### Changed
- **Simplified Scoring Logic**: Removed redundant CASE conditions for title/description LIKE matches
  - Old: 5 scoring conditions (exact code, prefix code, exact title, prefix title, infix description)
  - New: 3 scoring conditions (exact code, prefix code, exact title)
  - FULLTEXT relevance scoring handles the rest automatically

### Technical Details
- Modified files:
  - `includes/class-kiss-woo-coupon-lookup.php` - Added FULLTEXT index to schema, bumped DB version to 1.1
  - `includes/class-kiss-woo-coupon-search.php` - Replaced OR LIKE conditions with MATCH AGAINST
- Query performance improvement:
  - **Before**: `OR title LIKE '%term%'` - Full table scan on 360k rows
  - **After**: `MATCH(...) AGAINST('term*')` - Uses FULLTEXT index, sub-millisecond search
- All 38 PHPUnit tests passing

---

## [1.2.6] - 2026-01-28

### Added
- **Admin UI Button for Coupon Lookup Table Build**: Added "Build Lookup Table" button in settings page
  - New settings section "Coupon Lookup Table" with real-time progress tracking
  - Shows total coupons, indexed count, percentage complete, and build status
  - Background job runs via WP-Cron with rate limiting (60 seconds between batches)
  - Progress persists across page loads and server restarts
  - Cancel button to stop ongoing builds
  - Auto-polling UI updates every 3 seconds while building
  - **Impact**: Admins can now build coupon lookup table from UI without WP-CLI access
- **Shared Batch Processor Architecture**: Created `KISS_Woo_Coupon_Lookup_Builder` class
  - Single source of truth for batch processing logic
  - Used by both WP-CLI and admin UI background jobs
  - Implements locking to prevent concurrent builds
  - Rate limiting with configurable intervals
  - Progress tracking with status (idle, running, complete, error)
  - Graceful error handling and recovery from stuck locks
  - **Pattern**: Admin button and CLI both trigger same code path via different front-ends

### Changed
- **Updated WP-CLI Command**: Refactored `wp kiss-woo coupons backfill` to use shared builder class
  - Added `--reset` flag to reset progress and start from beginning
  - CLI bypasses rate limiting with `force=true` parameter
  - Improved progress messages using builder's unified format
  - Maintains backward compatibility with existing flags (`--batch`, `--start`, `--max`)
- **Background Job Handler**: Registered WP-Cron action `kiss_woo_coupon_build_batch`
  - Automatically schedules next batch if not complete
  - 60-second interval between batches (configurable via `MIN_RUN_INTERVAL` constant)
  - Self-healing: stuck locks timeout after 5 minutes

### Technical Details
- New files:
  - `includes/class-kiss-woo-coupon-lookup-builder.php` (313 lines) - Shared batch processor
  - `admin/js/kiss-woo-settings.js` (172 lines) - Settings page JavaScript
- Modified files:
  - `admin/class-kiss-woo-settings.php` - Added coupon section, AJAX handlers, asset enqueuing
  - `includes/class-kiss-woo-coupon-cli.php` - Refactored to use shared builder
  - `kiss-woo-fast-order-search.php` - Added builder include, WP-Cron action registration
- AJAX endpoints:
  - `kiss_woo_start_coupon_build` - Start background build
  - `kiss_woo_get_build_progress` - Poll for progress updates
  - `kiss_woo_cancel_coupon_build` - Cancel ongoing build
- WordPress options used:
  - `kiss_woo_coupon_build_progress` - Stores progress data
  - `kiss_woo_coupon_build_lock` - Prevents concurrent builds
  - `kiss_woo_coupon_build_next_run` - Rate limiting timestamp

---

## [1.2.5] - 2026-01-28

### Added
- **Lazy Backfill with Fallback Query**: Coupon search now works immediately without manual backfill
  - Added `fallback_search()` method that queries `wp_posts` directly when lookup table has no results
  - Added `lazy_backfill_coupons()` method that automatically indexes coupons found via fallback (up to 10 per search)
  - Added `format_from_coupon()` method to format WC_Coupon objects directly
  - **Impact**: Sites with 100k+ coupons can now use coupon search immediately without waiting for full backfill
  - Lookup table is populated gradually as users search, avoiding performance impact
  - Fallback results are marked with `source_flags: ['fallback']` for debugging
- **Persistent Search Scope Toggle**: Users/Orders vs Coupons toggle now remembers last used setting
  - Uses localStorage to persist scope selection across page loads
  - Applies to both admin search page and floating toolbar
  - URL parameter `?scope=coupons` still takes precedence for deep links
  - Gracefully falls back to 'users' if localStorage unavailable (private browsing)

### Fixed
- **Coupon Results Not Returned**: Fixed critical bug where coupon search results were not being sent to frontend
  - Root cause: AJAX handler was missing `coupons` key in response array
  - Solution: Added `coupons` and `search_scope` keys to response in `handle_search()` method
  - Impact: Coupon search now returns results to the UI correctly
  - Added `coupon_count` to debug logging for better troubleshooting
- **Auto-Redirect for Single Coupon**: When exactly 1 coupon is found, automatically redirect to coupon editor
  - Follows same pattern as order search (DRY principle)
  - Backend sets `should_redirect_to_order=true` and `redirect_url` when 1 coupon found
  - Frontend uses unified redirect logic for both orders and coupons
  - Updated toolbar and admin page JavaScript to handle coupon redirects
  - Added debug logging for coupon redirects

### Changed
- Updated version number to 1.2.5 in main plugin file

---

## [1.2.4] - 2026-01-28

### Fixed
- **Coupon Lookup Backfill Issue**: Fixed critical bug preventing coupon lookup table from being populated
  - Root cause: `wc_get_coupon()` helper function not available in WP-CLI and early admin contexts
  - Solution: Changed `KISS_Woo_Coupon_Lookup::upsert_coupon()` to use `new WC_Coupon()` directly instead of `wc_get_coupon()` helper
  - Impact: Coupon search now works correctly after backfilling 17,499 existing coupons
  - Added debug logging to `upsert_coupon()` for WP-CLI troubleshooting
  - Successfully backfilled all existing coupons via WP-CLI: `wp kiss-woo coupons backfill --batch=1000`

### Changed
- Updated version number to 1.2.4 in main plugin file

---

## [1.2.3] - 2026-01-09

### Security
- **PII Redaction in Error Logs**: Added automatic redaction of sensitive data before logging to `error_log()` (Critical security fix)
  - Implemented `redact_sensitive_data()` method in `KISS_Woo_Debug_Tracer` to prevent PII leaks in server logs
  - Redacts 14 sensitive keys: email, billing_email, shipping_email, search_term, customer_id, user_id, billing_phone, shipping_phone, addresses, IP address, user agent
  - Keeps first 3 characters for debugging context (e.g., "joh***" instead of full email)
  - Recursively redacts nested arrays to catch all sensitive data
  - Only affects error-level logs written to server logs; debug traces remain unredacted for authorized debugging
  - **Impact**: Prevents accidental PII exposure in production server logs while maintaining debugging capability
- **Production Console Logging**: Removed unconditional `console.log()` calls from admin JavaScript (Critical security fix)
  - Wrapped 5 unconditional console calls in debug flag checks (`if (KISSCOS.debug)`)
  - Affected calls: version check, invalid state transitions, duplicate submissions, response state warnings, redirect logging
  - Console output now only appears when `KISS_WOO_FAST_SEARCH_DEBUG` constant is enabled
  - **Impact**: Prevents operational details and search terms from leaking to browser console in production

### Changed
- Updated version number to 1.2.3 in main plugin file and admin JS

---

## [1.2.2] - 2026-01-09

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
- **Timeout Fallback**: Added 5-second safety timeout for toolbar redirect states (Audit item 4.2)
  - Automatically resets UI to IDLE if navigation is blocked (e.g., popup blocker)
  - Prevents users from being stuck with disabled input/button
  - Cleans up timeout on successful page navigation
  - Logs timeout events in debug mode for troubleshooting

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

