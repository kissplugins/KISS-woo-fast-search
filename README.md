# KISS - Faster Customer & Order Search

A WordPress/WooCommerce plugin that adds a streamlined admin page for quickly locating customers and their recent orders by email or name.

## Features
- WooCommerce submenu page for unified customer and order search.
- AJAX-powered results with permission and nonce checks for administrators.
- Displays key customer metadata, order counts, and quick links to profiles and orders.
- Optional benchmark page comparing default WooCommerce queries with the plugin's optimized lookups.

## Requirements
- WordPress 6.0+
- PHP 7.4+
- WooCommerce installed and active
- Administrator capability to access the search and benchmark pages

## Installation
1. Upload the plugin files to your WordPress installation (or clone into `wp-content/plugins/kiss-woo-fast-search`).
2. Activate **KISS - Faster Customer & Order Search** from the **Plugins** page.
3. Ensure WooCommerce is active; otherwise, an admin notice will prompt you to install it.

## Usage
1. In the WordPress admin, go to **WooCommerce → KISS Search**.
2. Enter a customer email, partial email, or name and submit to fetch matching customers.
3. Review customer details and recent orders directly from the results list; guest orders are shown when a valid email is provided.
4. To compare query performance, open **WooCommerce → KISS Benchmark**, enter a test email, and run the benchmark.

## Security & Performance Notes
- The AJAX endpoint requires `manage_woocommerce` or `manage_options` capability and validates requests with a nonce.
- Guest order lookups only run when the query is a valid email address to limit unnecessary database scans.
- See **AUDIT.md** for prioritized security and performance findings identified during the latest review.
