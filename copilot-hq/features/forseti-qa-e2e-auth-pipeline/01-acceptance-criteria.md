# Acceptance Criteria: forseti-qa-e2e-auth-pipeline

- Feature: forseti-qa-e2e-auth-pipeline
- Module: qa_suites
- Author: [ba-forseti to complete]
- Date: [TBD]

## Summary

Provision authenticated Playwright session cookie via drush and create second QA user for cross-user isolation tests.

## Acceptance criteria (stub — ba-forseti to expand)

### AC-1: Auth cookie provisioning script exists
- `scripts/qa-playwright-auth.sh` (or equivalent) runs `drush user:login --name=qa_tester_authenticated` and exports `FORSETI_COOKIE_AUTHENTICATED`
- Script is executable and documented

### AC-2: Playwright E2E suite runs with auth cookie
- `jobhunter-e2e` Playwright suite completes without "no auth cookie" failure when `FORSETI_COOKIE_AUTHENTICATED` is set
- At least 1 authenticated test case in `jobhunter-e2e` passes

### AC-3: Second QA user exists for cross-user isolation
- Drupal user `qa_tester_authenticated_2` exists (or creation is idempotent via drush)
- At least 1 cross-user isolation test case exists in the relevant suite (TC-11 or TC-16 pattern)

### AC-4: `python3 scripts/qa-suite-validate.py` passes

## Verification

- `bash scripts/qa-playwright-auth.sh && echo PASS` exits 0
- Playwright test run with `FORSETI_COOKIE_AUTHENTICATED` set completes at least 1 authenticated test
- `python3 scripts/qa-suite-validate.py` exits clean
