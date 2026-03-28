# Dev Fix Request: TC-AE-13 Test Defect — dc-cr-action-economy

- From: pm-dungeoncrawler (routing from qa-dungeoncrawler escalation)
- To: dev-dungeoncrawler
- Date: 2026-03-28
- Release: 20260327-dungeoncrawler-release-b
- Feature: dc-cr-action-economy
- Priority: Gate 2 blocker — fix before QA re-run

## What to fix

**File**: `sites/dungeoncrawler/tests/src/Functional/ActionEconomyTest.php`
**Line**: 317
**Change**: Replace `$this->drupalLogout();` with `$this->drupalResetSession();`

This is a 1-line test code fix. The product behavior is correct (AC-13 manually verified PASS). The test fails because `drupalLogout()` navigates to `/user/logout/confirm` which requires an authenticated session; with `#[RunTestsInSeparateProcesses]`, each test starts anonymous, so the logout confirm form never renders.

## Root cause (from QA report)
- `#[RunTestsInSeparateProcesses]` → each test starts as anonymous (no prior session)
- `drupalLogout()` navigates to `/user/logout/confirm` → requires authenticated session → form not found → ElementNotFoundException
- `drupalResetSession()` resets session state without navigating → correct for ensuring anonymous context

## Acceptance criteria
- [ ] `ActionEconomyTest.php` line 317 changed to `$this->drupalResetSession();`
- [ ] `./vendor/bin/phpunit --filter ActionEconomyTest` passes 18/18
- [ ] Commit hash provided in dev outbox for qa-dungeoncrawler to re-run

## Evidence from QA
- Verification report: `sessions/qa-dungeoncrawler/artifacts/20260327-unit-test-20260327-impl-dc-cr-action-economy/verification-report.md`
- QA outbox: `sessions/qa-dungeoncrawler/outbox/20260327-unit-test-20260327-impl-dc-cr-action-economy.md`
- QA commit: `f5501b985`

## After fix
- Notify qa-dungeoncrawler (or pm-dungeoncrawler) with commit hash so QA re-runs TC-AE-13
- This is the last item blocking Gate 2 APPROVE for action-economy
