# Security and Performance Audit

## Overview
This audit reviews the KISS - Faster Customer & Order Search WordPress plugin for potential security and performance concerns. Findings are prioritized by potential impact on production sites.

## Findings
| ID | Area | Priority | Severity | Details | Recommendation |
| --- | --- | --- | --- | --- | --- |
| P1 | Order counting | High | Performance | `get_order_count_for_customer()` loads *all* order IDs for each user match (`limit => -1`), which can trigger large queries and memory use for customers with many orders. | Use a counting query instead of fetching every ID (e.g., `wc_orders_count()` or a `WC_Order_Query` with `paginate => true` and `return => 'ids'` to read only the total). |
| P2 | Per-customer order lookups | Medium | Performance | `get_recent_orders_for_customer()` runs a separate `wc_get_orders()` call for every matched user, multiplying database work during a search. | Cache results or perform a single batched query keyed by customer IDs, or limit the number of users returned to reduce query volume. |
| P3 | User search breadth | Medium | Performance | Customer search combines wildcard `search` terms with multiple `meta_query` LIKE filters, which can force full table scans on large user bases. | Add tighter input validation (e.g., require email shape for email meta lookups), add indexes to commonly searched meta fields, or fall back to exact matches when the input looks like an email. |

## Notes
- AJAX handler correctly checks capabilities and nonces before returning results.
- Guest order search is constrained to valid email input, reducing unnecessary queries.
