- Status: done
- Summary: Identified and fixed the blocking implementation gap for `forseti-jobhunter-e2e-flow`. The controller (`toggleJobApplied`), POST route (`/jobhunter/my-jobs/{job_id}/applied` with `_csrf_token: TRUE`), and CSRF token generation (`apply_csrf_token` per job) were already in place from prior commits, but `my-jobs.html.twig` never rendered the `form.applied-toggle-form` that the Playwright E2E test (`markAppliedDataEngineer`) requires. Added the form to all job rows as a secondary operation: `form.applied-toggle-form` with `input[name="have_applied"]` (pre-checked when `status == 'applied'`), `input[name="applied_on_date"]`, and `button[type="submit"]` ("Update"). The form action includes `?token={{ job.apply_csrf_token }}` for CSRF validation. Form is available on all workflow states so users can record manual external applications at any stage. Committed `1f84c8539`. All other AC items (step-flow navigation, job save, queue infrastructure, stage break) were already implemented. PHP lint and `drush cr` confirm clean.

## Next actions
- QA (`qa-forseti`) should run Gate 2 verification for `forseti-jobhunter-e2e-flow`:
  - Run Playwright suite: `bash -c 'set -e; DRUSH=/var/www/html/forseti/vendor/bin/drush; ...'` (full command in suite.json `jobhunter-e2e`)
  - Verify `report.submission.success = true` in artifacts JSON
  - Verify `markAppliedDataEngineer` step succeeds (checkbox found, update submitted, success message shown)
- QA note: TC-07 (stage break network interception), TC-11 (queue failure injection), and TC-14 (mid-sequence rollback) are flagged as manual/future in the test plan — these do not block Gate 2

## Blockers
- None.

## Needs from CEO
- N/A

## ROI estimate
- ROI: 60
- Rationale: This was the last missing piece to make the Playwright E2E test runnable; unblocks Gate 2 for the primary P0 feature in release-c. The fix was a single template addition with zero schema/route changes.
