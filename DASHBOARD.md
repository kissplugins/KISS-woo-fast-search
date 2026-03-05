# KISS Woo Fast Search - Project Dashboard

---
client: kissplugins
repo: https://github.com/kissplugins/KISS-woo-fast-search
last_edit: 2026-03-05
week_of: 2026-03-02
current_version: 1.2.16
branch: development
---

## Current Status

**Version:** 1.2.16 | **Branch:** `development` | **PR:** [#46](https://github.com/kissplugins/KISS-woo-fast-search/pull/46) (Coupon Search)

All blocking issues resolved. Production-ready for merge to `main`.

---

## 1. Strategic Backlog

1. - [ ] **Guest order reassignment gap** — Orders reassigned from guest to customer with different email become invisible in search (P2). See [P2-GUEST-ORDERS.md](PROJECT/1-INBOX/P2-GUEST-ORDERS.md)
2. - [ ] **Client-side result filtering** — Add JS filter for current result set + "Show More" button (P2). See [P2-RESULTS-PAGINATION.md](PROJECT/1-INBOX/P2-RESULTS-PAGINATION.md)
3. - [ ] **Admin notice for empty coupon lookup table** — UX polish: warn if coupon search enabled but table not populated. See [PR-REVIEW-2026-03-04.md](PROJECT/2-WORKING/PR-REVIEW-2026-03-04.md)
4. - [ ] **Performance test with 100k+ coupons** — Validate FULLTEXT search at scale; verify sub-second response times

---

## 2. Current Week (2026-03-02)

- [x] **Merge feature/add-coupon-search into development** — Resolved conflicts, unified debug logging (v1.2.14)
- [x] **Fix N+1 fallback query** — Replaced per-coupon `WC_Coupon` loop with 2 batch SQL queries (v1.2.15)
- [x] **Remove debug files + hardcoded test values** — Deleted scratch files, cleared `r1m8jj1xt2m1m`, added `.gitignore` patterns (v1.2.16)
- [x] **PR review audit** — Verified all PR-REVIEW claims against codebase; updated README backfill docs status

---

## 3. Previous Week (2026-02-23)

- [x] **Coupon lookup table + FULLTEXT index** — Schema v1.1 with `MATCH AGAINST` replacing `LIKE '%term%'` (v1.2.7)
- [x] **Replace unconditional error_log()** — 7 calls converted to `KISS_Woo_Debug_Tracer::log()` (v1.2.8)
- [x] **Remove debug_log/is_debug_enabled** — Eliminated dead methods, converted 9 call sites to tracer (v1.2.8)
- [x] **Fix esc_like for FULLTEXT** — Replaced `$wpdb->esc_like()` with FULLTEXT operator stripping (v1.2.8)

---

## 4. Lessons Learned

1. **FULLTEXT > LIKE for multi-column search** — `OR title LIKE '%term%'` forces MySQL to abandon ALL indexes. FULLTEXT with BOOLEAN MODE provides sub-ms search on 360k+ rows.
2. **Audit CHANGELOG claims against code** — v1.2.8 claimed error_log removal was complete but 7 calls remained. v1.2.2 claimed debug_log removal but 9 call sites persisted. Always verify.
3. **N+1 queries hide behind "temporary" fallback paths** — The fallback `new WC_Coupon()` loop was "only for fresh installs" but was still the hot path until backfill completed. Batch loading (2 queries total) is always worth the effort.
4. **`$wpdb->esc_like()` is wrong for FULLTEXT** — It escapes `%` and `_` (LIKE operators), not `+`, `-`, `~`, `<`, `>` (FULLTEXT boolean operators). Different query types need different sanitization.

---

## Architecture Overview

| Component | File | Purpose |
|-----------|------|---------|
| Main plugin | [kiss-woo-fast-order-search.php](kiss-woo-fast-order-search.php) | Bootstrap, WP-Cron hooks |
| Search engine | [includes/class-kiss-woo-search.php](includes/class-kiss-woo-search.php) | Customer + order search (HPOS-aware) |
| Coupon search | [includes/class-kiss-woo-coupon-search.php](includes/class-kiss-woo-coupon-search.php) | FULLTEXT lookup + fallback |
| Coupon indexer | [includes/class-kiss-woo-coupon-lookup.php](includes/class-kiss-woo-coupon-lookup.php) | Lookup table schema, upsert, normalize |
| Batch builder | [includes/class-kiss-woo-coupon-lookup-builder.php](includes/class-kiss-woo-coupon-lookup-builder.php) | Background build with locking/rate-limiting |
| AJAX handler | [includes/class-kiss-woo-ajax-handler.php](includes/class-kiss-woo-ajax-handler.php) | Nonce/capability checks, response formatting |
| Debug tracer | [includes/class-kiss-woo-debug-tracer.php](includes/class-kiss-woo-debug-tracer.php) | Centralized logging, PII redaction |
| WP-CLI | [includes/class-kiss-woo-coupon-cli.php](includes/class-kiss-woo-coupon-cli.php) | `wp kiss-woo coupons backfill` |

---

## Audit Status

| Audit | Status | Doc |
|-------|--------|-----|
| Coupon Search PR Review | All blocking items fixed | [PR-REVIEW-2026-03-04.md](PROJECT/2-WORKING/PR-REVIEW-2026-03-04.md) |
| Security & Performance (Codex) | All actionable items resolved | [P1-AUDIT-CODEX-REVIEWED.md](PROJECT/2-WORKING/P1-AUDIT-CODEX-REVIEWED.md) |
| Claude Opus Audit | Triaged — all critical items fixed | [AUDIT-CLAUDE-OPUS.md](PROJECT/1-INBOX/AUDIT-CLAUDE-OPUS.md) |

---

## Release History (Recent)

| Version | Date | Key Change |
|---------|------|------------|
| 1.2.16 | 2026-03-04 | Remove debug files + hardcoded test values |
| 1.2.15 | 2026-03-04 | Fix N+1 fallback query (2 batch queries) |
| 1.2.14 | 2026-03-04 | Merge coupon search branch, unify debug logging |
| 1.2.13 | 2026-02-04 | Replace hardcoded PII email in benchmark page |
| 1.2.12 | 2026-02-03 | Fix wrong debug constant in list handlers |

See [CHANGELOG.md](CHANGELOG.md) for full history.
