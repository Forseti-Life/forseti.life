# Implementation Notes: forseti-jobhunter-application-controller-split

- Feature: forseti-jobhunter-application-controller-split
- Author: ba-forseti (stub) — dev-forseti to expand during implementation
- Phase: Phase 2 of 2 (Phase 1 = forseti-jobhunter-application-controller-db-extraction, shipped release-c)

## Approach (to be finalized by dev-forseti)

### Pre-work
1. Read `features/forseti-jobhunter-application-controller-db-extraction/02-implementation-notes.md` for Phase 1 context (constructor DI pattern, service injection, DB extraction map).
2. Audit `JobApplicationController.php` — categorize every public method as either:
   - **Render**: returns `Response`, `render array`, or calls `$this->view()`/`$this->render()` — goes to `ApplicationSubmissionController`
   - **Action/AJAX**: handles form submission, returns `JsonResponse`, or handles redirects post-action — goes to `ApplicationActionController`
3. Note any shared `protected` or `private` helpers — may need duplication or extraction to a shared trait.

### Split strategy
- Create `ApplicationSubmissionController.php` extending Drupal's `ControllerBase` (same as current class).
- Create `ApplicationActionController.php` extending Drupal's `ControllerBase`.
- Copy constructor and DI wiring verbatim from `JobApplicationController.php` into both new classes.
- Move methods (do not refactor signatures) — one method at a time to allow incremental verification.
- Update `job_hunter.routing.yml` as each method moves: change `_controller: '\Drupal\job_hunter\Controller\JobApplicationController::methodName'` to the correct new class.
- After all methods are moved: either delete `JobApplicationController.php` or reduce it to a thin stub (≤ 50 lines) if Drupal caches the old class reference.
- Run `drush cr` after each batch of routing changes.

### Routing update pattern
Before (example):
```yaml
job_hunter.my_jobs:
  path: '/jobhunter/my-jobs'
  defaults:
    _controller: '\Drupal\job_hunter\Controller\JobApplicationController::myJobs'
```
After:
```yaml
job_hunter.my_jobs:
  path: '/jobhunter/my-jobs'
  defaults:
    _controller: '\Drupal\job_hunter\Controller\ApplicationSubmissionController::myJobs'
```

### CSRF split-route preservation (critical)
- Do NOT collapse existing split-route pairs (GET + POST entries for the same path).
- When renaming the controller in routing.yml, only change the `_controller:` value; leave `_csrf_token`, `methods`, and permission lines untouched.
- Reference: KB lesson `knowledgebase/lessons/20260326-csrf-route-split-pattern.md` (or equivalent).

### Verification after each batch
```bash
cd /home/ubuntu/forseti.life/sites/forseti
./vendor/bin/drush cr
php -l web/modules/custom/job_hunter/src/Controller/ApplicationSubmissionController.php
php -l web/modules/custom/job_hunter/src/Controller/ApplicationActionController.php
./vendor/bin/drush router:debug | grep job_hunter
```

### File locations
- New controllers: `web/modules/custom/job_hunter/src/Controller/`
- Routing: `web/modules/custom/job_hunter/job_hunter.routing.yml`

## Cross-site sync
- N/A — job_hunter module is forseti.life only.

## Known risks
- Shared private/protected helpers: if both controller classes need the same helper, consider a `JobApplicationControllerTrait` to avoid duplication. Do not inline-duplicate complex logic.
- Constructor size: DI wiring in `JobApplicationController.php` may be large — copy it verbatim first, optimize later (Phase 3 scope).

## Dev sign-off checklist
- [ ] All AC from `01-acceptance-criteria.md` verified with commands
- [ ] `drush cr` exits 0 with no fatal errors in watchdog
- [ ] `git diff --stat` reviewed to confirm no unintended files changed
