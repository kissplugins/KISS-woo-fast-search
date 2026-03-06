# Index Hints for `wc_customer_lookup` Queries

**Date:** 2026-03-05
**Priority:** P3 (Backlog — only act if slow query logs appear)
**Effort:** 🟢 LOW (2–3 hours)
**Breaking Risk:** 🔴 MEDIUM-HIGH (see below)
**Status:** NOT STARTED

---

## Context

`search_user_ids_via_customer_lookup()` in `includes/class-kiss-woo-search.php` runs up to 3
queries against `wc_customer_lookup`. On sites with 100k+ customers, MySQL may choose a
suboptimal query plan for the OR-heavy WHERE clauses.

---

## The Three Query Modes

### 1. `prefix_multi_column` (line 277) — Primary path, most searches hit this
```sql
SELECT user_id FROM wc_customer_lookup
WHERE user_id > 0
AND (email LIKE 'term%' OR first_name LIKE 'term%' OR last_name LIKE 'term%' OR username LIKE 'term%')
ORDER BY date_registered DESC LIMIT 40
```
**Problem:** 4-way OR defeats single-index usage. MySQL may do a full table scan even though
`email` has an index. An `USE INDEX` hint targeting `email` index could help for email-like
terms, but the OR across unindexed columns (`first_name`, `last_name`) limits gains.

### 2. `name_pair_prefix` (line 241) — "John Smith" style searches
```sql
SELECT user_id FROM wc_customer_lookup
WHERE user_id > 0
AND ((first_name LIKE 'john%' AND last_name LIKE 'smith%') OR ...)
ORDER BY date_registered DESC LIMIT 40
```
**Problem:** `first_name` and `last_name` have **no default WooCommerce indexes**.
No index hint can help here — the only fix would be adding custom indexes (separate task).

### 3. `contains_email_fallback` (line 303) — Infix email fallback
```sql
SELECT user_id FROM wc_customer_lookup
WHERE user_id > 0 AND email LIKE '%term%'
ORDER BY date_registered DESC LIMIT 40
```
**Problem:** Infix `LIKE '%term%'` always does a full scan. No index hint can help.
Already mitigated by: only runs when prefix search returns 0 results AND term contains `@`.

---

## What Index Hints Could Actually Help

Only `prefix_multi_column` is a candidate, and only when the term looks like an email
(so MySQL can lean on the `email` index). A `USE INDEX (email)` hint there would suppress
the multi-column OR plan and force a range scan on email, returning early.

```php
// Candidate change — prefix_multi_column mode only, email-ish terms
$hint = $is_emailish ? 'USE INDEX (email)' : '';
$sql = $wpdb->prepare(
    "SELECT user_id FROM {$table} {$hint}
     WHERE user_id > 0
     AND (email LIKE %s OR first_name LIKE %s OR last_name LIKE %s OR username LIKE %s)
     ORDER BY date_registered DESC LIMIT %d",
    ...
);
```

---

## Effort

| Step | Time |
|------|------|
| Verify actual index names on `wc_customer_lookup` via `SHOW INDEX` | 30 min |
| Add conditional `USE INDEX` hint to `prefix_multi_column` path | 1 hour |
| Run EXPLAIN before/after on staging with 100k+ rows | 1 hour |
| Update unit tests (SQL string matching) if any | 30 min |
| **Total** | **~3 hours** |

---

## Breaking Risk: 🔴 MEDIUM-HIGH

Index hints are **fragile** for a plugin:

1. **WooCommerce may rename indexes** in a future release — a hint referencing a
   non-existent index name causes a MySQL error, breaking all customer searches.
2. **`first_name` / `last_name` have no WC-provided indexes** — hints can't help the
   most common name search path without adding custom indexes (schema change).
3. **Minimal gain in practice** — queries are already LIMITed to 40 rows and the table
   fits in the MySQL buffer pool on most servers.

**Mitigation if implementing:** Wrap hint in a runtime `SHOW INDEX` check to confirm the
index exists before injecting it. Adds overhead but prevents hard failures.

---

## Should We Add Custom Indexes on `first_name`/`last_name` Instead?

Better than index hints (no fragile name dependency), but **table ownership is the blocker**:

- `wc_customer_lookup` is a WooCommerce-managed table — we don't own its schema
- `dbDelta()` can't be used; requires raw `ALTER TABLE ADD INDEX`
- `ALTER TABLE` on a 100k+ row table is a **blocking operation** — risky to run silently on plugin activation
- WooCommerce writes to this table on every order/customer update — extra indexes add write overhead
- Still only partially solves the problem: helps `name_pair_prefix` mode, partially helps `prefix_multi_column` (4-way OR), does nothing for infix email fallback

**Decision: Do not add indexes automatically. Provide as a manual DBA step.**

If slow queries are confirmed on a specific site, document the fix for the site owner to run deliberately:

```sql
-- Run manually on sites with 100k+ customers if name searches are slow.
-- Confirm slow queries first via EXPLAIN and slow query log.
ALTER TABLE wp_wc_customer_lookup ADD INDEX kiss_idx_first_name (first_name(50));
ALTER TABLE wp_wc_customer_lookup ADD INDEX kiss_idx_last_name (last_name(50));
```

---

## Recommendation

**Do not implement speculatively.** Only act if:
- Slow query log shows `wc_customer_lookup` queries exceeding 500ms consistently
- Site has 100k+ customers AND symptom is reproducible with `EXPLAIN`

If that threshold is hit, provide the manual SQL above as a documented DBA step — do not
run `ALTER TABLE` silently from plugin code.

