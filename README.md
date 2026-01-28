# KISS - Faster Customer & Order Search

A lightweight WordPress plugin that adds a WooCommerce admin page for quickly searching customers and their orders by email or name. The plugin exposes an authenticated AJAX endpoint that surfaces matching customers and recent orders, plus a basic benchmark page comparing native WooCommerce queries with the optimized lookups used here.

## Requirements
- WordPress 6.0+
- PHP 7.4+
- WooCommerce active
- An administrator or shop manager role (capability `manage_woocommerce`) to access the pages and AJAX endpoint.

Populating Existing Coupons (Backfilling): The plugin has two ways to add your existing coupons to the lookup table:

**On-Demand (Lazy Backfill):** When you search for a coupon, if it isn't in the fast lookup table yet, the plugin finds it using the standard (slower) WordPress method and then automatically adds it to the lookup table. This makes all future searches for that coupon instant.

**Manual Trigger** (WP-CLI): For sites with many existing coupons, the plugin provides a wp kiss-woo coupons backfill command. This allows a developer to efficiently populate the entire lookup table from the command line, processing thousands of coupons in batches.

## Features
- Admin submenu under WooCommerce with a simple search form for customers and guest orders.
- Capability and nonce checks on the AJAX handler to prevent unauthorized access.
- Server-side validation ensuring search terms are trimmed and at least two characters long.
- Localized strings and AJAX configuration injected via `wp_localize_script` for a smooth JS experience.
- Benchmark page (`WooCommerce → KISS Benchmark`) that runs search performance comparisons.

## Usage
1. Activate the plugin in WordPress.
2. Navigate to **WooCommerce → KISS Search**.
3. Enter an email address, partial email, or customer name, then submit the form.
4. Results display matching users, counts of their orders, recent orders, and guest order matches (for email input).
5. To profile performance, visit **WooCommerce → KISS Benchmark** and run the test for any email.

## Security & Performance Notes
- The AJAX endpoint only allows users with `manage_woocommerce` or `manage_options` and enforces a nonce, but customer/order data is inserted into the admin page via JavaScript without escaping. See `AUDIT.md` for the recommended fix.
- Customer searches currently load full user records with all meta and count orders via unbounded WooCommerce queries; this may be slow on stores with many users/orders. Optimizations are outlined in `AUDIT.md`.

## Development
- Source resides in the plugin root with supporting classes under `admin/` and `includes/`.
- JavaScript for the admin experience lives in `admin/kiss-woo-admin.js`.
