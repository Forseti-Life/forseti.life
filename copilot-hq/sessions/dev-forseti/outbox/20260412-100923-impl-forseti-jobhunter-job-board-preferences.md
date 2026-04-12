# Outbox: impl-forseti-jobhunter-job-board-preferences

- Status: done
- Summary: Implemented the `forseti-jobhunter-job-board-preferences` feature in full. DB schema (AC-4) added via `job_hunter_update_9051` — creates `jobhunter_source_preferences` with uid (unique), sources_enabled (JSON text), min_salary, remote_preference, location_radius_km, created, changed. Preferences form at `/jobhunter/preferences/sources` (AC-2) renders source checkboxes for the four-source allowlist (linkedin/indeed/glassdoor/ziprecruiter), salary minimum, remote preference selector, and radius field. `sourcePreferencesSave()` (AC-1, AC-5) performs an idempotent upsert — UID always from session (SEC-3), SEC-4 allowlist enforced per source key, SEC-5 bounds validated (salary ≤ 999999999, radius 1–500). CSRF split-route pattern applied (SEC-2): GET page has no CSRF, POST `/save` route has `_csrf_token: 'TRUE'`. `JobDiscoveryService::getSourcePreferences(uid)` added for AC-3 — returns enabled sources and constraints; callers skip adapters not in the list. Unauthenticated access returns 403 (SEC-1). Update hook ran cleanly; schema verified.

## Commit
- `c68e2789c` — feat(job-hunter): add job-board source preferences (update 9051)

## Verification
- `drush updb -y` → `job_hunter_update_9051` completed: `[success] Finished performing updates.`
- `drush sql:query "DESCRIBE jobhunter_source_preferences"` → all 8 columns present (AC-4 ✅)
- `SHOW INDEX FROM jobhunter_source_preferences` → unique key on `uid` (AC-4 ✅)
- `curl https://forseti.life/jobhunter/preferences/sources` → HTTP 403 unauthenticated (SEC-1 ✅)
- Routing: GET route has no `_csrf_token`, POST save route has `_csrf_token: 'TRUE'` (SEC-2 ✅)
- `php -l` syntax check passes on all modified files

## Next actions
- qa-forseti: Gate 2 verification for `forseti-jobhunter-job-board-preferences` (all 5 functional ACs + 5 security ACs)

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 20
- Rationale: Source preference filtering reduces noise in job discovery results, directly improving user experience in the Job Hunter flow. Completes the fourth of the active release-d features, unblocking Gate 2 for this item.
