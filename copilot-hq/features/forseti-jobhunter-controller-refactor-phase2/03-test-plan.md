# Test Plan — forseti-jobhunter-controller-refactor-phase2

- Feature: forseti-jobhunter-controller-refactor-phase2
- Module: job_hunter
- QA owner: qa-forseti
- Status: groomed (next-release)
- AC source: 01-acceptance-criteria.md
- KB references: forseti-ai-service-refactor precedent (same DB extraction pattern; prior lessons in knowledgebase/lessons/)

## Scope summary

Internal refactor only — extract `$this->database` calls from `JobApplicationController` into `ApplicationSubmissionService` (or new `ApplicationAttemptService`). No new routes, permissions, or UI changes. Regression risk is behavioural: if extracted queries change semantics, submission steps silently break.

## Test cases

### TC-01: Zero direct DB calls in controller (AC-1)

| Field | Value |
|---|---|
| Suite | `forseti-jobhunter-controller-refactor-phase2-static` (unit/static) |
| Command | `grep -c '\$this->database' web/modules/custom/job_hunter/src/Controller/JobApplicationController.php` |
| Expected | Returns `0` |
| Roles | N/A (static analysis) |
| Automation | Yes — bash static check |

### TC-02: Service methods exist and are documented (AC-2)

| Field | Value |
|---|---|
| Suite | `forseti-jobhunter-controller-refactor-phase2-static` (unit/static) |
| Command | `grep -c 'public function' web/modules/custom/job_hunter/src/Service/ApplicationSubmissionService.php` returns > 0; `grep -c '/\*\*' ApplicationSubmissionService.php` > 0 |
| Expected | At least 1 public method; at least 1 PHPDoc block per extracted query |
| Roles | N/A |
| Automation | Yes — bash static check |

### TC-03: Service registered in services.yml (AC-2)

| Field | Value |
|---|---|
| Suite | `forseti-jobhunter-controller-refactor-phase2-static` (unit/static) |
| Command | `grep 'ApplicationSubmissionService\|ApplicationAttemptService' web/modules/custom/job_hunter/job_hunter.services.yml` |
| Expected | At least one matching entry with `@database` or DB-level dependency |
| Roles | N/A |
| Automation | Yes — bash static check |

### TC-04: PHP lint clean — controller and service (AC-5)

| Field | Value |
|---|---|
| Suite | `forseti-jobhunter-controller-refactor-phase2-static` (unit/static) |
| Command | `php -l .../JobApplicationController.php && php -l .../ApplicationSubmissionService.php` |
| Expected | Both exit 0 with "No syntax errors detected" |
| Roles | N/A |
| Automation | Yes — bash static check |

### TC-05: Application submission steps 1–5 render without PHP errors (AC-3)

| Field | Value |
|---|---|
| Suite | `forseti-jobhunter-controller-refactor-phase2-functional` (functional/e2e) |
| Command | ALLOW_PROD_QA=1 curl GET `/jobhunter/application-submission` authenticated; check HTTP 200 or 302 redirect to step1 |
| Expected | HTTP 200 or 302 (no 500); no PHP error in Drupal watchdog |
| Roles | Authenticated (`access job hunter`) |
| Automation | Partially — curl smoke test; full E2E requires Playwright (deferred, Node absent) |
| Note to PM | Full multi-step POST flow (steps 3/4/5) requires Playwright or a logged-in session. Recommend manual smoke test OR Playwright when Node is installed. Mark as risk-accepted if Playwright remains deferred at ship time. |

### TC-06: Existing application-submission ACL regression (AC-4)

| Field | Value |
|---|---|
| Suite | Re-run existing `forseti-jobhunter-application-submission-route-acl` suite entry |
| Command | Existing suite command (see qa-suites/products/forseti/suite.json line 42) |
| Expected | Same as current baseline: anon=deny, authenticated=allow for all `/jobhunter/application-submission/*` and `/application-submission/*` paths |
| Roles | Anonymous + Authenticated |
| Automation | Yes — already in suite.json, no new entry needed |

### TC-07: Existing CSRF checks still pass (AC-4)

| Field | Value |
|---|---|
| Suite | Re-run existing CSRF static check suite entry (suite.json ~line 195) |
| Command | Existing suite command — checks all 7 step POST routes have `_csrf_token: 'TRUE'` |
| Expected | All 7 routes PASS (no regression from refactor) |
| Roles | N/A (static YAML check) |
| Automation | Yes — already in suite.json, no new entry needed |

## Suite entries to activate at Stage 0

One new suite entry to add at Stage 0 (DO NOT add now):

```json
{
  "id": "forseti-jobhunter-controller-refactor-phase2-static",
  "label": "Controller refactor phase2: zero direct DB calls in controller, service methods present, services.yml registered, PHP lint clean",
  "type": "unit",
  "feature_id": "forseti-jobhunter-controller-refactor-phase2",
  "command": "bash -c 'set -e; CTRL=/home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter/src/Controller/JobApplicationController.php; SVC=/home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter/src/Service/ApplicationSubmissionService.php; echo \"TC-01 zero DB calls:\"; COUNT=$(grep -c \"\\$this->database\" \"$CTRL\" 2>/dev/null || echo 0); [ \"$COUNT\" -eq 0 ] && echo \"PASS: 0 direct DB calls\" || (echo \"FAIL: $COUNT direct DB calls remain\" && exit 1); echo \"TC-02 service methods:\"; MC=$(grep -c \"public function\" \"$SVC\"); [ \"$MC\" -gt 0 ] && echo \"PASS: $MC public methods\" || (echo \"FAIL: no public methods\" && exit 1); echo \"TC-03 services.yml:\"; grep -q \"ApplicationSubmissionService\\|ApplicationAttemptService\" /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter/job_hunter.services.yml && echo \"PASS: service registered\" || (echo \"FAIL: service not registered\" && exit 1); echo \"TC-04 lint controller:\"; php -l \"$CTRL\" && echo \"TC-04 lint service:\"; php -l \"$SVC\"'",
  "artifacts": [
    "sessions/qa-forseti/artifacts/jobhunter-controller-refactor-phase2-static-latest/"
  ],
  "run_notes": [
    "Static-only suite. TC-05 (POST flow smoke test) requires authenticated session; deferred to functional suite when Playwright/drush available.",
    "TC-06 and TC-07 reuse existing application-submission-route-acl and CSRF suite entries."
  ]
}
```

## Non-automatable items (note to PM)

- **TC-05 full POST flow** (steps 3/4/5 POST with real form data): requires authenticated browser session or Playwright. Currently deferred due to Node/Playwright absent on host. Recommend risk-acceptance at ship time or blocking on Playwright install before release.
- The existing unit suite entry (`forseti-jobhunter-application-submission-unit`) covers ApplicationSubmissionService in isolation — QA will re-run this at Stage 0 to confirm no regression.

## Regression risk areas

1. Query semantics change during extraction (silent data bug) — mitigated by AC-4 requiring existing tests pass
2. Service injection missing or wrong argument order — caught by TC-03 + TC-04 lint
3. Controller still retaining `$this->database` via a helper call — caught by TC-01 grep
