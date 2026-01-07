# KISS Woo Fast Search — Red Flag Audit (Codex)

## Scope
Reviewed the core admin search flow, search strategies, and performance tooling for security/performance red flags.

## Findings

| Severity | Area | Location | Red Flag | Recommendation |
| --- | --- | --- | --- | --- |
| Medium | Customer search fallback | `includes/search/class-hypercart-wp-user-query-strategy.php` (`search_by_name_pair`, `search_by_single_term`) | Fallback `WP_User_Query` relies on `meta_query` with `LIKE` comparisons across `billing_*` usermeta and a wildcard `search` query. This can force full scans on large usermeta tables and degrade admin responsiveness on big stores. | Keep as last-resort only, add tighter limits/timeout logging, and consider migrating heavy lookups to indexed customer lookup tables or precomputed fields. |
| Low | Benchmark query sanitization | `admin/class-kiss-woo-benchmark.php` (`run_tests`) | Benchmark uses `esc_attr()` for the WooCommerce order search term and passes `$query` directly into `WP_User_Query::search`. `esc_attr()` is HTML-focused and the benchmark method accepts raw input. | Normalize with `sanitize_text_field()`/`wc_clean()` inside the benchmark helper to ensure query-safe values even if called directly. |
| Low | Order summary rendering | `includes/class-kiss-woo-search.php` (`format_order_for_output`) + `admin/kiss-woo-admin.js` (`renderOrdersTable`) | Order totals are returned as HTML from `wc_price()` and injected into the DOM without escaping in JS. If a third-party filter alters price HTML, it could create an admin-side XSS vector. | Strip tags server-side or HTML-escape `order.total` client-side before insertion. |
| Low | Recent orders batch query | `includes/class-kiss-woo-search.php` (`get_recent_orders_for_customers`) | Direct SQL `IN` query against `_customer_user` meta values can be slow on very large datasets where `postmeta.meta_value` isn’t indexed. The query is capped but still a potential hotspot. | Consider using indexed lookup tables or batching via order IDs from WooCommerce APIs when available, with caching to reduce repeat load. |

## Notes
- AJAX search is protected by capability checks and nonces.
- Direct SQL queries are properly prepared and appear scoped to admin-only functionality.
