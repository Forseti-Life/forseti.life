# Acceptance Criteria: forseti-jobhunter-application-controller-split

- Feature: forseti-jobhunter-application-controller-split
- Author: pm-forseti
- Date: 2026-04-09
- KB reference: Phase 1 context in `features/forseti-jobhunter-application-controller-db-extraction/`

## Summary

Structural split of `JobApplicationController.php` (4177 lines) into two controllers. Pure refactor — no behavior changes, no new DB calls, all existing routes and permissions preserved.

## Acceptance criteria

### AC-1: Controller files created
- `ApplicationSubmissionController.php` exists at `web/modules/custom/job_hunter/src/Controller/ApplicationSubmissionController.php`.
- `ApplicationActionController.php` exists at `web/modules/custom/job_hunter/src/Controller/ApplicationActionController.php`.
- `JobApplicationController.php` is either removed or reduced to a thin delegating stub ≤ 50 lines.

### AC-2: Route correctness
- All routes in `job_hunter.routing.yml` previously pointing to `JobApplicationController::*` now point to the correct split controller method.
- Zero broken routes: `drush router:debug | grep job_hunter` must show all expected routes.

### AC-3: Line count
- Each new controller file is ≤ 800 lines.

### AC-4: Syntax clean
- `php -l web/modules/custom/job_hunter/src/Controller/ApplicationSubmissionController.php` exits 0.
- `php -l web/modules/custom/job_hunter/src/Controller/ApplicationActionController.php` exits 0.

### AC-5: Zero new DB calls
- `grep -c '\$this->database' web/modules/custom/job_hunter/src/Controller/ApplicationSubmissionController.php` = 0
- `grep -c '\$this->database' web/modules/custom/job_hunter/src/Controller/ApplicationActionController.php` = 0

### AC-6: Auth/permission surface unchanged
- Authenticated GET on `/jobhunter/my-jobs` returns 200.
- Anonymous GET on `/jobhunter/my-jobs` returns 403 or redirect (no change from current).
- Smoke: `curl -o /dev/null -s -w "%{http_code}" https://forseti.life/jobhunter/my-jobs` = 403 anonymous.

### AC-7: CSRF tokens preserved
- All POST routes that previously used the split-route pattern (`_csrf_token: TRUE` on POST-only entry) retain that configuration in `job_hunter.routing.yml`. No collapse of split routes.
- Verify: `grep -A5 "_csrf_token" sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml` returns same count as before.

### AC-8: Cache rebuild passes
- `drush cr` exits 0 with no PHP fatal errors in watchdog after the split.

## Non-goals (explicitly out of scope)
- Business logic extraction (Phase 3 deferred)
- Test file creation (QA follow-on)
- Any new feature additions
