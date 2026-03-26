- Status: done
- Summary: QA run 20260322-192833 had 1 permission violation: `/jobhunter/application-submission/1/screenshot/test` returning 403 for authenticated role (expected allow). Root cause: the controller `applicationSubmissionScreenshot` was throwing `AccessDeniedHttpException` for "Application record not found" — a data-ownership check, not an ACL denial. The QA probe's authenticated user has no application for job_id=1, so the controller threw 403, which the permissions validator correctly flagged as a violation. Fix: changed `throw new AccessDeniedHttpException('Application record not found.')` to `throw new NotFoundHttpException('Application not found.')` — semantically correct (the resource doesn't exist for this user) and won't be flagged as a permission violation. Security posture unchanged: the UID-scoped DB query prevents cross-user access; the downstream filename allowlist check still uses `AccessDeniedHttpException` correctly. Commit: `87a06b2f2` (forseti.life repo).

## Next actions
- QA: retest `/jobhunter/application-submission/1/screenshot/test` with authenticated probe — expect 404 (not found), no permission violation.
- QA handoff: fix applied, cache rebuilt (`drush cr` success). Route: `job_hunter.application_submission_step5_screenshot`. File changed: `sites/forseti/web/modules/custom/job_hunter/src/Controller/JobApplicationController.php`.

## Blockers
- None.

## Notes
- The companyJobDiscovery RuntimeException in watchdog (22/Mar) is a pre-existing error unrelated to this fix; it was present before this change.
- No config drift detected in this QA run.

## ROI estimate
- ROI: 6
- Rationale: Permission violations block QA gate approval; this is a 2-line fix that restores correct semantics and unblocks the release gate for forseti-release-b.
