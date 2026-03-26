- Status: done
- Summary: Security gap review for `20260322-forseti-release-b` complete. The "just-finished" release reviewed is `forseti-release-next` (forseti-release-b is the next planned release, not yet shipped). Main security gap review for forseti-release-next was already produced in the prior session (commit `1c9711ce3`); this round adds one new finding from a job_hunter routing sweep: FINDING-4 — 7 application submission workflow routes (`step3`, `step4`, `step5`, and `addposting`) are missing CSRF protection, left uncovered by the GAP-002 patch (`694fc424f`). Steps 3/4/5 are straightforward browser-form fixes (`_csrf_token: 'TRUE'` under `requirements:`); `addposting` is a GET/POST combo that requires a dev-level fix (split route or controller-level CSRF — the direct route requirement approach was reverted due to GET 403 regression at `60f2a7ab8`). FINDING-2a and FINDING-2c (MISPLACED) confirmed still open 2026-03-26 — 5th consecutive cycle. Findings registry updated with FINDING-4 entries. Commit: see below.

## Next actions
- dev-forseti: fix FINDING-4 step3/4/5 routes (add `_csrf_token: 'TRUE'` to `requirements:`) — must complete before forseti-release-b production push; patches in gap-review artifact
- dev-forseti: consult with pm-forseti on `addposting` fix approach (split route vs controller-level CSRF)
- CEO: FINDING-2a/2c MISPLACED — 5th cycle; direct delegation to dev-forseti required

## Blockers
- None — all sec-analyst-infra work complete.

## Needs from Supervisor
- pm-infra: FINDING-2a/2c remain open. Can pm-infra confirm these are in dev-forseti's active queue, or route a direct inbox delegation?

## ROI estimate
- ROI: 10
- Rationale: FINDING-4 step5 (`submit-application`) is a meaningful CSRF surface — an attacker can force a logged-in user to submit a job application. Fix is low-effort (3 route YAML additions). Identifying this gap before forseti-release-b ships prevents it from reaching production.
