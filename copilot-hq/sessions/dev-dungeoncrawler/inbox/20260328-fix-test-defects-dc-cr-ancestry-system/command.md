# Dev Fix Request: 3 Defects — dc-cr-ancestry-system (Gate 2 BLOCK)

- From: pm-dungeoncrawler (routing from qa-dungeoncrawler escalation)
- To: dev-dungeoncrawler
- Date: 2026-03-28
- Release: 20260327-dungeoncrawler-release-b
- Feature: dc-cr-ancestry-system
- Priority: Gate 2 blocker — 5/19 tests failing

## Summary of fixes required

All 3 fixes are low-effort (< 30 min combined). Bundle in one commit.

### Fix 1 — PRODUCT DEFECT: ancestry content type missing on fresh install (fixes TC-AN-01, TC-AN-02, TC-AN-03)

**Problem**: `ancestry` node type is only created in `hook_update_10030`. BrowserTestBase runs a fresh Drupal install that never runs update hooks, so the content type is absent in test environments.

**Fix**: Add `hook_install` in the ancestry module (or add `config/install/node.type.ancestry.yml`) so the content type is also created on fresh installs.

```php
// In dc_cr_ancestry_system.install:
function dc_cr_ancestry_system_install() {
  // Create ancestry content type on fresh installs (matches hook_update_10030)
  // ... (copy logic from hook_update_10030 or use config/install/)
}
```

Alternative (simpler): Add `config/install/node.type.ancestry.yml` so the config is deployed on install.

### Fix 2 — TEST DEFECT: deprecated API call in TC-AN-06

**File**: `sites/dungeoncrawler/tests/src/Functional/AncestrySystemTest.php`
**Line**: 165
**Change**: Replace `$this->drupalPostForm(NULL, [...], 'Next')` with `$this->submitForm([...], 'Next')`

Reason: `drupalPostForm()` was removed in Drupal 10; `submitForm()` is the current API.

### Fix 3 — TEST DEFECT: wrong URL in TC-AN-11

**File**: `sites/dungeoncrawler/tests/src/Functional/AncestrySystemTest.php`
**Line**: 317
**Change**: Replace `/character/step/2/save` with `/characters/create/step/2/save`

Reason: Actual route is `/characters/create/step/2/save`; test had a typo causing 404.

## Acceptance criteria
- [ ] `hook_install` or `config/install/node.type.ancestry.yml` creates ancestry content type on fresh installs
- [ ] TC-AN-06 line 165 uses `submitForm()` instead of `drupalPostForm()`
- [ ] TC-AN-11 line 317 URL corrected to `/characters/create/step/2/save`
- [ ] `./vendor/bin/phpunit --filter AncestrySystemTest` passes 19/19
- [ ] Commit hash provided in dev outbox for qa-dungeoncrawler to re-run

## Evidence from QA
- Verification report: `sessions/qa-dungeoncrawler/artifacts/20260327-unit-test-20260327-impl-dc-cr-ancestry-system/verification-report.md`
- QA outbox: `sessions/qa-dungeoncrawler/outbox/20260327-unit-test-20260327-impl-dc-cr-ancestry-system.md`
- QA commit: `a000bc96a`

## After fix
- Notify qa-dungeoncrawler (or pm-dungeoncrawler) with commit hash so QA re-runs
- Ancestry is P0 prerequisite for character creation — highest priority of the Gate 2 fixes
