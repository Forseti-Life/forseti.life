- Status: done
- Summary: Retroactive improvement round processed for dungeoncrawler-release-b (dispatched 20260322; original 20260227 review was too shallow). Three concrete process gaps identified from this cycle: (1) no pre-deploy schema validation step — the `genai-debug` 500 (missing columns in `ai_conversation_api_usage`) slipped into QA undetected because `drush updatedb` silently reported no pending updates despite the table being incomplete; (2) CSRF token generator misuse on a GET endpoint — `btn-save-job` rendered an empty `data-csrf-token` because the controller called the token generator for `job_hunter.addposting`, a route that should not use route-level CSRF; this caused E2E Playwright false failures across multiple cycles; (3) the original 20260227 improvement round outbox for this cycle was filed as "no blockers, no stale paths" while two active bugs (schema drift, empty CSRF token) were open — the improvement round process was not reviewing QA artifact evidence before filing done. Seat instructions already updated with CSRF GET+POST constraint (commit e899093b1). Schema validation gap documented here for follow-through.

## Next actions
- Add pre-deploy schema validation step to `dev-forseti` seat instructions: run `drush sqlq "DESCRIBE <table>"` when `drush updatedb` reports no pending updates but a controller crashes with Unknown column.
- Flag to `pm-forseti`: improvement round reviews must include a scan of open QA violations and outbox items before filing `Status: done`; "no blockers" is only valid if the QA evidence confirms it.
- No code changes required; both bugs are fixed (schema patched by CEO direct SQL; CSRF route constraint applied in e899093b1).

## Blockers
- None.

## Process gaps identified

### GAP-A: No pre-deploy schema validation step
- **What happened**: `genai-debug` controller references 4 columns that were missing from `ai_conversation_api_usage`. `drush updatedb` reported "no pending updates" because the update hook had already been marked run (DB restore / partial reinstall). The 500 was caught only by QA, not by dev pre-handoff.
- **Owner**: `dev-forseti` (checklist update); `dev-infra` (drush schema audit automation if desired)
- **SMART fix**: Add checklist item to seat instructions: "If a controller crashes with `Unknown column` after `drush updatedb` shows no pending updates, run `drush sqlq 'DESCRIBE <table>'` and compare against controller field references before filing done."
- **Acceptance criteria**: Seat instructions contain the schema drift diagnostic step; exercised in next dungeoncrawler update-hook cycle.
- **ROI**: 6 — eliminates a QA-catchable-only failure class with a 10-second diagnostic check.

### GAP-B: CSRF token generator misused on GET endpoint
- **What happened**: `btn-save-job` links rendered `data-csrf-token=""` because the controller called `$this->csrfTokenGenerator->get('job_hunter.addposting')` for a GET-only endpoint. Empty token caused E2E Playwright tests to fall back to `window.location.href` navigation, which the test harness swallowed silently. Root cause: using route-level CSRF token generator on a GET route that (correctly) has no `_csrf_token: 'TRUE'` requirement. The fix is to not generate a route-level CSRF token for GET endpoints — the JS save path should use the action-URL directly.
- **Owner**: `dev-forseti` (already documented in CSRF GET+POST constraint, commit e899093b1)
- **SMART fix**: Constraint is now in seat instructions. Follow-up: verify `btn-save-job` renders a non-empty CSRF token or that the JS path no longer depends on it (use direct POST endpoint instead).
- **Acceptance criteria**: `btn-save-job` E2E test passes without the empty-token fallback path; `data-csrf-token` is either populated or JS is refactored to not require it.
- **ROI**: 7 — E2E false failures erode test suite confidence; this pattern is recurrently costly.

### GAP-C: Improvement round review process was too shallow
- **What happened**: The 20260227 outbox for this same release cycle (`20260227-improvement-round-20260227-dungeoncrawler-release-b.md`) was filed as "no blockers, no stale paths" while both the schema drift and empty CSRF token bugs were active open issues. The improvement round process was not scanning QA artifacts or open outbox items before concluding.
- **Owner**: `dev-forseti` (process discipline)
- **SMART fix**: Add to improvement round delivery discipline (already in seat instructions): "Before filing Status: done, scan the most recent QA violation report and open outbox items for any issue that wasn't addressed; list each with status."
- **Acceptance criteria**: Next improvement round outbox for this seat explicitly references QA artifact path and confirms zero open violations, or lists known open items with owner and ROI.
- **ROI**: 5 — prevents repeat pattern of improvement rounds filing done while active bugs are open.

## ROI estimate
- ROI: 6
- Rationale: Schema drift and CSRF misuse are both recurrent patterns that cost full QA cycles; documenting them in seat instructions and the checklist eliminates the failure class at near-zero cost.
