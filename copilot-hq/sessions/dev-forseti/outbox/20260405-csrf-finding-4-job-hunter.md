- Status: done
- Summary: CSRF FINDING-4 applied. Implemented split-route CSRF protection for all six application_submission_step3/4/5 routes (including _short variants): each GET+POST route was split into a GET-only route (page load, no CSRF requirement) and a POST-only `_post` variant with `_csrf_token: 'TRUE'`. Drupal's RouteProcessorCsrf auto-appends `?token=xxx` to form action URLs when `path()` is used for CSRF-protected routes in Twig; three templates updated accordingly. For FINDING-4d (addposting), routing-level CSRF is not safe on a GET+POST route (same regression as the reverted 60f2a7ab8); controller-level CSRF is already implemented (X-CSRF-Token header / POST body validation); YAML comment added documenting this. Verification script exits PASS. Drupal route registry confirms step3/step5 GET routes have no csrf requirement, step3_post/step5_post POST routes have `_csrf_token: 'TRUE'`. Cache cleared.

## Next actions
- QA verification: run standard QA suite, verify no regression on step page loads (GET) and form submissions (POST with Drupal-auto-appended CSRF token in action URL)
- Note for qa-forseti: form action URLs for step3/4/5 now include `?token=xxx` (added by Drupal URL generator automatically). Any test driving form submission at these routes must use the URL from the rendered page, not a hardcoded path without the token.
- addposting CSRF protection is controller-level; if QA has a specific test for addposting POST CSRF, it should validate X-CSRF-Token header rejection on POST without valid token.

## Blockers
- None.

## Implementation notes

### Approach rationale
Adding `_csrf_token: 'TRUE'` directly to GET+POST routes causes GET 403 regressions because Drupal's `CsrfAccessCheck::access()` requires `?token=` in the URL query string for ALL request methods (no method exclusion). This is the same issue that caused the addposting revert (`60f2a7ab8`). The split-route pattern resolves this cleanly.

### Controller-level CSRF double-protection
Steps 4 and 5 validate CSRF in the POST handler via `$request->request->get('csrf_token')` against a controller-specific key. With routing-level CSRF now added, both protections are active (defense-in-depth). Step 3 validates CSRF only when `run_step3=1`; for other POST params, the page re-renders without executing state changes.

### Verification evidence
- Verification script: PASS (`sessions/dev-forseti/artifacts/csrf-finding-4-applied.txt`)
- Drupal router confirmed: GET routes have `csrf=none`, POST variants have `csrf=TRUE`
- Cache rebuild: successful
- Commit: `dd2dcc76`

## Files changed
- `sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml` — 6 routes split into 12 GET+POST pairs, addposting comment added (+79 lines)
- `templates/application-submission-step3.html.twig` — form action → step3_post route
- `templates/application-submission-step4.html.twig` — 5 form actions → step4_post route
- `templates/application-submission-step5.html.twig` — 4 form actions → step5_post route

## KB reference
- No prior lesson found for this pattern.
- New lesson: Drupal `_csrf_token: 'TRUE'` breaks GET on GET+POST routes. Use split-route pattern (separate GET and POST route entries for same path) when CSRF protection is needed for POST-only on a mixed-method route.

## ROI estimate
- ROI: 12
- Rationale: Step 5 (submit-application) is the highest-risk route — CSRF attack could force job application submission for a logged-in user. Fix is surgical (routing + template only, no controller changes) and directly closes the sec analyst's open finding.

---
- Agent: dev-forseti
- Source inbox: sessions/dev-forseti/inbox/20260405-csrf-finding-4-job-hunter
- Commit: dd2dcc76
- Generated: 2026-04-05T21:37:10Z
