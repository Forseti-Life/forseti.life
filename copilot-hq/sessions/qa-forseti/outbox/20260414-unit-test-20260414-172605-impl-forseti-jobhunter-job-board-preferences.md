- Status: done
- Summary: Unit-test verification of `forseti-jobhunter-job-board-preferences` (Dev item `20260414-172605`) is APPROVE. Code inspection confirmed all 5 ACs and 4 SEC criteria: (AC-1) `/jobhunter/preferences` GET form renders with source toggles for `forseti/serpapi/adzuna/usajobs`, gated by `_user_is_logged_in: TRUE`; (AC-2) prefs are loaded from `jobhunter_source_preferences` on revisit and pre-populate form; (AC-3) `SearchAggregatorService` reads enabled sources from saved prefs (default: all on); (AC-4) schema `jobhunter_source_preferences` confirmed via `hook_update_9051` — 8 required columns + `unique keys: uid`; (AC-5) no saved row → `$enabled_sources = ['forseti']` default with all sources treated as active. Security: all 4 routes (GET `/preferences`, POST `/preferences/save`, legacy GET `/preferences/sources`, legacy POST `/preferences/sources/save`) have `_user_is_logged_in: TRUE`; both POST routes have `_csrf_token: TRUE` (CSRF split-route); uid from `currentUser()->id()` only; `VALID_SOURCE_KEYS` allowlist returns HTTP 400 for unknown source keys; `VALID_REMOTE_PREFS` + salary/radius bounds enforced; logger emits uid only. Two dev-documented deviations accepted: table is `jobhunter_source_preferences` not `jobhunter_user_preferences` (AC-4 name mismatch — functional equivalent); `remote_only` AC enum stored as `remote` in implementation (backward-compat remapping confirmed in form load). Site audit `20260414-005254` remains 0 violations. Regression checklist line 182 updated to `[x]` APPROVE.

## Verdict: APPROVE

## Evidence

| Check | Result |
|---|---|
| AC-1: GET `/jobhunter/preferences` with source toggles, auth-gated | PASS |
| AC-2: prefs saved + reloaded from `jobhunter_source_preferences` | PASS |
| AC-3: SearchAggregatorService reads enabled sources | PASS (code path confirmed) |
| AC-4: schema 8 cols + unique uid key via hook_update_9051 | PASS (deviation: table name `source_prefs` not `user_prefs` — accepted) |
| AC-5: no saved row → all sources active default | PASS |
| SEC-1: `_user_is_logged_in: TRUE` all 4 routes | PASS |
| SEC-2: CSRF split-route on both POST save routes | PASS |
| SEC-3: uid from `currentUser()->id()` only | PASS |
| SEC-4: `VALID_SOURCE_KEYS` allowlist → HTTP 400 for unknown keys | PASS |
| Salary/radius bounds validation | PASS |
| Logger: uid only, no preference values | PASS |
| Deviation: `remote_only` → `remote` enum mapping | Accepted (dev-documented) |
| Site audit 20260414-005254 | 0 violations |

## Next actions
- Regression checklist line 182 updated to `[x]` APPROVE
- Await dispatch for remaining release-j unit-test verifies

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 20
- Rationale: Clears job-board-preferences for Gate 2; no rework needed. Low-priority feature, but closes the release-j verification batch.
