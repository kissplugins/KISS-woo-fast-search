# P1 - Coupon Search (Performance-First) Specification

## Table of Contents
1. Overview
2. Goals
3. Non-Goals
4. User Stories
5. Scope & Requirements
6. Data Sources & Plugin Compatibility
7. Performance Strategy
8. Data Model & Indexing
9. Search Flow & Ranking
10. Caching Strategy
11. Permissions & Security
12. Edge Cases
13. Telemetry & Debugging
14. Testing Plan
15. Rollout Plan
16. Risks & Mitigations
17. Open Questions

## Phased Checklist (High Level)
Note for LLM: Continuously mark checklist items as completed as progress is made.
- [x] Phase 0: Discovery + acceptance criteria confirmed
- [x] Phase 1: Data model + indexing designed
- [x] Phase 2: Search API + scoring implemented
- [x] Phase 3: UI integration + results rendering
- [x] Phase 4: Backfill + migration tooling
- [x] Phase 5: Test coverage + perf validation
- [x] Phase 6: Release + monitoring

## Status: ✅ COMPLETE (v1.2.5)

### Completed Features
- [x] Coupon lookup table with indexed search fields
- [x] Lazy backfill with fallback query (no manual backfill required)
- [x] Auto-redirect for single coupon results
- [x] Persistent search scope toggle (localStorage)
- [x] Unified redirect logic for orders and coupons (DRY)
- [x] All 38 tests passing
- [x] CHANGELOG updated with v1.2.5 release notes

---

## 1. Overview
Create a fast, admin-only coupon search for WooCommerce that is significantly more performant than WooCommerce's default coupon search (which relies on post meta queries). The search must include coupons created by third-party plugins Smart Coupons and Advanced Coupons, and return accurate results quickly even with large coupon datasets.

This spec should follow best practices for KISS Woo Fast Search architecture and conventions.

- DRY
- Single contract writers
- Separation of concerns
- Defensive error handling


## 2. Goals
- Provide instant coupon search (sub 5s for common queries on 100k+ coupons in for high volume hosting).
- Search across native WooCommerce coupons and coupons from Smart Coupons and Advanced Coupons.
- Minimize database load by using indexed lookup tables and lean queries.
- Return consistent results for coupon code, title, description, and configured meta (usage restrictions, amounts, etc.).
- Align with existing KISS AJAX search payload format and UI patterns.

## 3. Non-Goals
- No front-end customer-facing coupon search.
- No coupon creation or editing UI changes.
- No full-text search across arbitrary meta keys not explicitly supported.
- No breaking changes to existing WooCommerce or plugin behavior.

## 4. User Stories
- As a store admin, I can quickly find a coupon by code, even if created by Smart Coupons or Advanced Coupons.
- As support, I can search by partial code, title, or description to confirm coupon details.
- As a manager, I can retrieve results without timeouts on large stores.

## 5. Scope & Requirements
Functional
- Search input supports:
  - Coupon code (exact and partial)
  - Coupon post title
  - Coupon description
  - Optional: discount amount, free shipping, expiry date
- Results must include:
  - Coupon ID, code, type, amount, expiry, usage limits
  - Source plugin flags (core / smart / advanced)
  - Edit link

Performance
- Prefer indexed lookup tables; avoid unbounded meta joins.
- Cap results (default 20) with predictable sorting.
- Avoid N+1 lookups for coupon metadata.

Compatibility
- Work with WooCommerce coupons (shop_coupon post type).
- Include coupons created by:
  - Smart Coupons
  - Advanced Coupons
- Must not assume plugin activation at runtime; degrade gracefully.

## 6. Data Sources & Plugin Compatibility
WooCommerce core
- Coupons are stored as posts with post_type = 'shop_coupon'.
- Core fields:
  - post_title (coupon code), post_excerpt (description)
  - meta: discount_type, coupon_amount, date_expires, usage_limit, etc.

Smart Coupons
- Often adds meta keys for store credit, gift certificates, or auto-apply behavior.
- Uses same shop_coupon post type; additional metadata influences applicability.

Advanced Coupons
- Adds extended restrictions (e.g., product categories, shipping zones) stored in meta keys.
- Uses shop_coupon; metadata includes advanced rule fields.

Compatibility approach
- Index key fields from core + known plugin meta keys when present.
- Keep a configurable list of plugin meta keys to index.
- Avoid hard failures if plugin meta does not exist.

## 7. Performance Strategy
Baseline issue
- WooCommerce default search relies on WP_Query with meta_query and like clauses that scale poorly.

Proposed
- Create a dedicated lookup table for coupons with indexed columns for common search fields.
- Use precomputed normalized fields to support fast LIKE queries.
- Fetch matching coupon IDs from the lookup table, then hydrate minimal data.

Performance targets
- 10k coupons: 0-250ms for partial search
- 100k coupons: under 1s for common prefix search

## 8. Data Model & Indexing
Create a lookup table (example name):
- `wp_kiss_woo_coupon_lookup`

Columns (suggested)
- coupon_id (BIGINT, PK)
- code (VARCHAR, indexed)
- code_normalized (VARCHAR, indexed)
- title (VARCHAR)
- description (TEXT)
- description_normalized (TEXT)
- amount (DECIMAL)
- discount_type (VARCHAR)
- expiry_date (DATE or DATETIME)
- usage_limit (INT)
- usage_count (INT)
- free_shipping (TINYINT)
- source_flags (SET or JSON) // core, smart, advanced
- updated_at (DATETIME)

Indexes
- PRIMARY KEY (coupon_id)
- KEY idx_code_normalized (code_normalized)
- KEY idx_title (title)
- KEY idx_expiry (expiry_date)

Normalization
- Lowercase, trim, strip punctuation for code_normalized.
- Optional: store a searchable tokenized version for description (if needed).

Indexing pipeline
- On coupon create/update: upsert into lookup table.
- On coupon delete: remove from lookup table.
- Provide a CLI or admin tool to backfill/rebuild.
  - CLI: `wp kiss-woo coupons backfill --batch=500`

## 9. Search Flow & Ranking
Input handling
- Normalize term: lowercase, trim, remove wildcard characters.
- If input looks like coupon code prefix, prioritize code_normalized.

Toolbar auto-scope heuristic (JS-only, proposal — do NOT implement until refined)
- Goal: auto-switch toggle to “Coupons” when the user is clearly typing a coupon code; avoid false positives.
- Rules (stay on Users/Orders if ANY are true):
  - Contains space: `/\s/`
  - Contains `@`: `/@/`
  - Purely numeric, 4–8 digits: `/^\d{4,8}$/`
  - Known order prefix pattern: `/^(?:B|D|WC)\d+$/i`
  - Length > 25 chars
- Rules (hint Coupons if ALL are true):
  - Single token (no spaces)
  - Length 5–15 chars
  - Contains at least one letter AND one digit: `/(?=.*[A-Za-z])(?=.*\d)/`
  - User has not manually toggled
- UX guardrails:
  - If the user manually toggles, stop auto-switching until page refresh.
  - Optional: “hint” (flash/outline) instead of force-switch on borderline matches.

Exact JS logic placeholder (for review only)
```js
function shouldStayUsers(term) {
  if (/\s/.test(term)) return true;
  if (/@/.test(term)) return true;
  if (/^\d{4,8}$/.test(term)) return true;
  if (/^(?:B|D|WC)\d+$/i.test(term)) return true;
  if (term.length > 25) return true;
  return false;
}

function shouldHintCoupons(term, userOverrodeScope) {
  if (userOverrodeScope) return false;
  if (/\s/.test(term)) return false;
  if (term.length < 5 || term.length > 15) return false;
  if (!/(?=.*[A-Za-z])(?=.*\d)/.test(term)) return false;
  return true;
}
```

Query flow
1. Query lookup table for code/title/description matches.
2. Retrieve coupon IDs sorted by relevance.
3. Hydrate details from lookup + minimal post data (avoid full meta fetch).

Ranking heuristics
- Exact code match > prefix match > title match > description match.
- Boost active (non-expired) coupons.
- Stable sort by updated_at or coupon_id.

Result payload
- Align with existing AJAX response shape in KISS search.

## 10. Caching Strategy
- Use short-lived object cache for frequent queries (e.g., 30-120s).
- Cache key: term + role + site_id.
- Avoid caching if query is empty or too long.

## 11. Permissions & Security
- Restrict to users with manage_woocommerce or edit_shop_coupons capability.
- Use prepared statements for lookup queries.
- Escape output in JSON payloads (consistent with existing formatter).

## 12. Edge Cases
- Coupons with identical codes across sites (multisite): namespace by blog_id.
- Legacy coupons missing certain meta: allow NULLs.
- Plugins adding custom meta not in index list: still searchable by code/title.
- Large descriptions: truncate or avoid full text indexing if DB engine limits.

## 13. Telemetry & Debugging
- Add optional debug info: query time, rows scanned, result count.
- Provide a debug flag similar to existing KISS debug mode.

## 14. Testing Plan
Unit tests
- Lookup table upsert with core coupon only.
- Lookup upsert when Smart Coupons meta present.
- Lookup upsert when Advanced Coupons meta present.

Integration tests
- Search by exact code
- Search by partial code
- Search by description
- Expired coupon ranking demotion

Performance tests
- Synthetic 10k/100k coupons lookup time.
- Validate no unbounded meta joins in query plan.

## 15. Rollout Plan
- Phase 1: Add lookup table + backfill command (no UI usage)
- Phase 2: Implement new search endpoint for coupons
- Phase 3: Add UI integration behind feature flag
- Phase 4: Enable by default after validation

## 16. Risks & Mitigations
- Risk: Missing plugin-specific fields
  - Mitigation: Keep plugin meta list configurable and extendable
- Risk: Backfill takes too long
  - Mitigation: Chunked backfill + progress indicators
- Risk: Search results not matching WooCommerce defaults
  - Mitigation: Provide fallback to WC search when lookup table is empty

## 17. Open Questions
- Which exact Smart Coupons and Advanced Coupons meta keys must be indexed?
- Should we include usage restriction fields (products, categories) in search?
- Should expired coupons be hidden or shown by default?
- Where should the admin UI display coupon results in the existing KISS results panel?
