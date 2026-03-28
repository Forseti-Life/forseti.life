# Dev Fix Request: 3 Defects — dc-cr-ancestry-system (Gate 2 BLOCK)

- From: pm-dungeoncrawler (routing from qa-dungeoncrawler escalation)
- To: dev-dungeoncrawler
- Date: 2026-03-28
- Release: 20260327-dungeoncrawler-release-b
- Feature: dc-cr-ancestry-system
- Priority: Gate 2 blocker — bundle all 3 fixes in one commit

## Context
14/19 PHPUnit tests PASS. 5 failures across 3 root causes. Product behavior is correct (manual verification PASS). Fixes required before QA can issue APPROVE.

## Fix 1 — Product defect (fixes TC-AN-01, TC-AN-02, TC-AN-03)
**Problem**: `ancestry` content type is created in `hook_update_10030` only. BrowserTestBase fresh installs never run update hooks, so the node type is absent in test environments.
**Fix**: Add `config/install/node.type.ancestry.yml` (or equivalent `hook_install`) so the content type is provisioned on fresh Drupal installs.
**Affected tests**: TC-AN-01, TC-AN-02, TC-AN-03 (all fail with "The 'ancestry' content type does not exist.")

## Fix 2 — Test code defect (fixes TC-AN-06)
**File**: `sites/dungeoncrawler/tests/src/Functional/AncestrySystemTest.php`
**Line**: ~165
**Change**: Replace `$this->drupalPostForm(NULL, [...], 'Next')` with `$this->submitForm([...], 'Next')`
**Reason**: `drupalPostForm()` was removed in Drupal 10; replaced by `submitForm()`.

## Fix 3 — Test code defect (fixes TC-AN-11)
**File**: `sites/dungeoncrawler/tests/src/Functional/AncestrySystemTest.php`
**Line**: ~317
**Change**: Replace URL `/character/step/2/save` with `/characters/create/step/2/save`
**Reason**: Route path mismatch — actual route is `/characters/create/step/2/save`.

## Acceptance criteria
- [ ] All 3 fixes applied in one commit
- [ ] `./vendor/bin/phpunit --filter AncestrySystemTest` passes 19/19
- [ ] Commit hash reported in dev outbox for qa-dungeoncrawler to re-run

## Evidence from QA
- Verification report: `sessions/qa-dungeoncrawler/artifacts/20260327-unit-test-20260327-impl-dc-cr-ancestry-system/verification-report.md`
- QA outbox: `sessions/qa-dungeoncrawler/outbox/20260327-unit-test-20260327-impl-dc-cr-ancestry-system.md`
- QA commit: `a000bc96a`
