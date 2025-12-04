# Security and Performance Audit

This audit reviews the current codebase for the KISS - Faster Customer & Order Search plugin. Each finding is assigned a priority (P1 = highest) and severity (High/Medium/Low).

## Findings

| Priority | Severity | Area | Details | Recommendation |
| --- | --- | --- | --- | --- |
| P1 | High | Admin results rendering | Customer and order fields returned by the AJAX handler are concatenated directly into HTML in `admin/kiss-woo-admin.js` without escaping, so any untrusted values stored in names, emails, or order metadata could be rendered as HTML/JS in the admin view. | Escape all dynamic fields before injection (e.g., sanitize in PHP and/or HTML-escape in JS) or build DOM nodes with `textContent` to avoid XSS risk. |
| P1 | Medium | Order counting | `get_order_count_for_customer()` calls `wc_get_orders` with `limit => -1`, which loads every order object to count them. On stores with many orders this can exhaust memory and slow the response. | Use a lightweight count query (e.g., `wc_orders_count()` or a `WP_Query` with `'fields' => 'ids'` and `'no_found_rows' => true`, or a direct SQL `COUNT(*)`) to avoid loading full order objects. |
| P2 | Medium | Customer lookup efficiency | Customer searches request `fields => 'all_with_meta'` and an OR `meta_query` with leading wildcard `LIKE` clauses. This pulls all metadata and prevents index use, which can be slow on large user tables. | Limit fields to IDs/basic columns, fetch only required meta, and consider normalizing frequently searched fields or adding indexed columns to reduce full-table scans. |
| P3 | Low | Benchmark search sanitization | Benchmark page builds a `wc_get_orders` search string with `esc_attr($query)`; the request parameter is sanitized, but `esc_attr` is intended for HTML, not queries. | Apply `sanitize_text_field`/`wc_clean` consistently and rely on WooCommerce query args without HTML escaping, keeping the search term unescaped until rendered. |

## Additional Notes
- Capability checks (`manage_woocommerce`/`manage_options`) and nonces are present on the AJAX handler, reducing exposure to unauthorized callers.
- Guest-order search is constrained to valid email strings and capped at 20 results, limiting load from that path.
