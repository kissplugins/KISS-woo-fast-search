## Findings (security/perf‑focused)

#1 - HTML escaping	Medium	Not an issue	❌ No
#2 - LIKE scans	Medium	Intentional design	❌ No
#3 - Fallback perf	Medium	Fixed in v1.2.5	✅ Already done
#4 - Missing method	Low	Fixed in v1.2.5	✅ Already done

- **Medium (Security/Reliability):** Potential HTML-escaping in JSON response. `esc_url()` encodes `&` to `&#038;`, which can break redirects in JS. Prefer raw URL in JSON; escape only when rendering into HTML. `includes/class-kiss-woo-search.php:167`


- **Medium (Performance):** non‑sargable `LIKE` scans on the lookup table can devolve into full scans at scale. `includes/class-kiss-woo-coupon-search.php:70-90` uses leading‑wildcard `LIKE` on `title` and `description_normalized`.

- **Medium (Performance):** fallback search hits `wp_posts` with `post_title LIKE %term%` plus N+1 `WC_Coupon` loads, which is expensive when the lookup table is empty/out‑of‑date. `includes/class-kiss-woo-coupon-search.php:145-175`.

- **Low (Reliability):** `KISS_Woo_Coupon_Formatter::format_from_coupon()` is called but not implemented, which will fatal on the fallback path. `includes/class-kiss-woo-coupon-search.php:171-174`.

## Security scan notes
- I didn’t find obvious high‑risk security issues in this pass (nonces + `sanitize_text_field` are used in AJAX/admin flows).

## Open questions / assumptions
- Is the fallback search intended to be part of Phase 2? It wasn’t in the plan, and it will be a perf hotspot until the lookup is backfilled.
- Do we want to allow infix search on coupon titles/descriptions, or can we limit to prefix to keep index use?
- If you want, I can tighten the coupon query to remain index‑friendly and add the missing formatter method (or remove the fallback entirely).
