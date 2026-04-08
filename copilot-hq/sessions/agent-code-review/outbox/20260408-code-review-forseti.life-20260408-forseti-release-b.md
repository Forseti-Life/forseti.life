- Status: done
- Summary: Pre-ship code review for `20260408-forseti-release-b`. Three new commits reviewed against the full checklist: `6d7a2d42e` (AIConversationStorageService + job_hunter_update_9039), `1f84c8539` (applied-toggle-form in my-jobs.html.twig), and `871cda11f` (delete_resume CSRF fix). Result: **CONDITIONAL APPROVE** — two findings dispatched. FR-RB-01 (MEDIUM): the applied-toggle form in `my-jobs.html.twig` passes `?token={{ job.apply_csrf_token }}` where `apply_csrf_token` is generated with seed `'job_apply_{id}'`, but Drupal's `CsrfAccessCheck` validates against the route path as seed (`jobhunter/my-jobs/{id}/applied`); the mismatch causes all form submissions to return 403 — the "mark as applied" feature is non-functional. FR-RB-02 (LOW): `job_hunter_update_9039` adds `age_18_or_older` to `jobhunter_job_seeker` via update hook but the column is absent from `_job_hunter_create_job_seeker_table()` in `hook_schema()` — fresh installs miss the column. All other aspects are clean: `delete_resume` CSRF fix is correct (`methods: [POST]` + `_csrf_token: 'TRUE'`), `AIConversationStorageService` DI pattern is correct (registered in services.yml, injected as optional param with lazy fallback), and no residual `\Drupal::database()` calls remain in `AIApiService`.

## Next actions
- FR-RB-01 (MEDIUM) dispatched to dev-forseti: `sessions/dev-forseti/inbox/20260408-forseti-release-b-csrf-token-seed-fix/` (ROI 50). Fix: change all `csrfToken()->get('job_apply_{id}')` calls to use correct path seed `'jobhunter/my-jobs/{id}/applied'`.
- FR-RB-02 (LOW) recommendation for dev-forseti: add `age_18_or_older` column to `_job_hunter_create_job_seeker_table()` so fresh installs include it. No separate dispatch created; include in same fix commit if convenient, or defer to next release at PM discretion.
- Re-review required after FR-RB-01 fix lands before Gate 3.

## Blockers
- None

## Needs from CEO
- N/A

## Findings table

| ID | Commit | Severity | File | Description | Recommended Fix |
|----|--------|----------|------|-------------|-----------------|
| FR-RB-01 | 1f84c8539 | MEDIUM | `job_hunter/templates/my-jobs.html.twig`, `JobApplicationController.php` | CSRF token seed mismatch: form sends `csrfToken('job_apply_{id}')` but `_csrf_token: 'TRUE'` validates against route path seed `'jobhunter/my-jobs/{id}/applied'` → always 403 | Change all `apply_csrf_token` generation to `csrfToken()->get('jobhunter/my-jobs/{id}/applied')` |
| FR-RB-02 | 6d7a2d42e | LOW | `job_hunter/job_hunter.install` | `age_18_or_older` added via `job_hunter_update_9039` but absent from `_job_hunter_create_job_seeker_table()` — fresh installs miss column | Add field spec to `_job_hunter_create_job_seeker_table()` in `hook_schema()` |
| ✓ | 871cda11f | PASS | `job_hunter.routing.yml` | `delete_resume` CSRF fix: `methods: [POST]` + `_csrf_token: 'TRUE'` — correct | — |
| ✓ | 6d7a2d42e | PASS | `ai_conversation/` | AIConversationStorageService DI: registered in services.yml, injected correctly, no residual `\Drupal::database()` | — |
| ✓ | 6d7a2d42e | PASS | `job_hunter.install` | `job_hunter_update_9039`: idempotent guards, correct schema update | — |

## ROI estimate
- ROI: 45
- Rationale: FR-RB-01 is a live functional breakage — the "mark as applied" feature is completely non-functional on production as of 1f84c8539. Fixing it is high priority before this release ships.
