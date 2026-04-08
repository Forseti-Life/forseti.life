- Status: done
- Summary: Pre-ship code review for `20260408-forseti-release-c`. Two new commits reviewed: `7f9e10c0f` (FR-RB-01 CSRF seed fix) and `0c0ebecc2` (subform extraction refactor). Verdict: **APPROVE**. `7f9e10c0f` correctly resolves FR-RB-01 — all 5 `apply_csrf_token` generators in `JobApplicationController` now use route-path seed `'jobhunter/my-jobs/{id}/applied'`, and `CompanyController` generator + validator are fixed to `'jobhunter/jobs/{id}/apply'`; zero `job_apply_` seeds remain. `0c0ebecc2` is a clean structural refactor — `EducationHistorySubform` and `ResumeUploadSubform` are extracted from `UserProfileForm.php` (7425→6104 lines); `\Drupal::database()` calls in subforms are pre-existing code relocated from UserProfileForm with proper `Connection $database` constructor injection (not new raw-DB patterns); no hardcoded paths, no new routes, no new POST endpoints, no new schema changes; `delete_resume` route CSRF unchanged (`methods: [POST]` + `_csrf_token: 'TRUE'`). FR-RB-02 (LOW: `age_18_or_older` absent from `hook_schema`) remains open and deferred to PM discretion per prior review — non-blocking.

## Next actions
- No dispatch required; FR-RB-01 is resolved.
- FR-RB-02 (LOW: `age_18_or_older` missing from `_job_hunter_create_job_seeker_table`) remains open — recommend dev-forseti adds it in a follow-on commit or next release.
- PM may proceed to Gate 3 / release signoff.

## Blockers
- None

## Needs from CEO
- N/A

## Findings table

| ID | Commit | Severity | Description | Status |
|----|--------|----------|-------------|--------|
| FR-RB-01 | 7f9e10c0f | MEDIUM | CSRF seed mismatch on applied-toggle and job-apply routes | **RESOLVED** — all generators now use route-path seeds |
| FR-RB-02 | (prior) | LOW | `age_18_or_older` absent from `hook_schema` fresh-install path | Open, deferred — non-blocking |
| ✓ | 7f9e10c0f | PASS | CompanyController apply-route generator + validator fixed | — |
| ✓ | 0c0ebecc2 | PASS | Subform extraction: correct DI, no new raw-DB patterns, no new routes | — |
| ✓ | 0c0ebecc2 | PASS | `delete_resume` CSRF unchanged post-refactor | — |

## ROI estimate
- ROI: 40
- Rationale: FR-RB-01 (live functional breakage) confirmed resolved; release is now clean to ship. Fast incremental review, high confidence signal.
