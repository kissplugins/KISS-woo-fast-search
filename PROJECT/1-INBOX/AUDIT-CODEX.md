# Audit: Red Flags (Codex)

Scope: quick red-flag review of security/performance risks in the current KISS Woo Fast Search plugin.

## Findings

| ID | Priority | Area | Red flag | Why it matters | Recommendation |
| --- | --- | --- | --- | --- | --- |
| F-01 | P1 | Customer search fallback | `Hypercart_WP_User_Query_Strategy` relies on `meta_query` with `LIKE` clauses for `billing_first_name`, `billing_last_name`, and `billing_email`. | `LIKE` on usermeta with no dedicated indexes tends to force full table scans and can degrade sharply on large stores. | Prefer a lookup table (e.g., `wc_customer_lookup`) or dedicated indexed columns; at minimum, constrain the fallback to smaller result sets and/or add usermeta indexes where feasible. |
| F-02 | P2 | Customer search fallback | The `search` parameter is built as `'*' . esc_attr( $term ) . '*'` in `WP_User_Query`. | `esc_attr` is for HTML contexts, not queries, and the leading/trailing wildcard (`*term*`) disables index use, which can be slow under load. | Use `sanitize_text_field`/`wc_clean` for query sanitization and consider removing the leading wildcard for performance when possible. |
| F-03 | P3 | Admin results rendering | `order.total` is inserted into HTML without escaping in `admin/kiss-woo-admin.js`. | `wc_price()` returns HTML and is typically safe, but it can be filtered by third-party code; unescaped insertion could allow unintended markup if filters are compromised. | Consider whitelisting known-safe markup or rendering totals via DOM nodes with `textContent` plus formatting, depending on acceptable formatting needs. |

## Notes

- The AJAX handler uses capability checks and a nonce, which reduces exposure to unauthorized calls.
- The search pipeline includes caching and query monitoring, which mitigates some performance risk but does not eliminate the fallback query costs on large datasets.
