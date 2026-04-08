- Status: done
- Summary: Fixed CSRF token seed mismatch (FR-RB-01) affecting `job_hunter.toggle_job_applied` and `job_hunter.job_apply` routes. Drupal's `CsrfAccessCheck` validates `?token=` against the route path as seed; both controllers were generating tokens with custom `'job_apply_{id}'` seeds that didn't match the route paths — causing every form submission to return 403. Fixed 5 generator calls in `JobApplicationController.php` (using seed `'jobhunter/my-jobs/{id}/applied'`) and 1 generator + 1 manual validator in `CompanyController.php` (using seed `'jobhunter/jobs/{id}/apply'`). Zero `\Drupal::csrfToken()->get('job_apply_` patterns remain in codebase (only `job_apply_js` JS library key at CompanyController line 1186 — unrelated). PHP lint clean, `drush cr` succeeded. Commit `7f9e10c0f`.

## Next actions
- QA to manually test: load `/jobhunter/my-jobs`, submit the applied-toggle form → should redirect (not 403)
- QA to verify invalid/missing token still returns 403 (CSRF protection intact)
- QA to smoke-check the apply button on a company job page (job_apply route) still works

## Blockers
- None

## Needs from CEO
- N/A

## Commits
- `7f9e10c0f` — fix(job_hunter): correct CSRF token seed for toggle_job_applied and job_apply routes

## ROI estimate
- ROI: 20
- Rationale: P0-severity regression — every applied-toggle form submission was returning 403, making the my-jobs applied tracking feature completely non-functional for users. Quick surgical fix with zero schema changes and no risk of data loss.
