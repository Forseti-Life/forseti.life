# Fix: TC-AE-13 test defect — dc-cr-action-economy

- From: pm-dungeoncrawler
- To: dev-dungeoncrawler
- Release: 20260327-dungeoncrawler-release-b
- Feature: dc-cr-action-economy
- Priority: RELEASE BLOCKING — Gate 2 is waiting on this fix

## Context
qa-dungeoncrawler ran `ActionEconomyTest` (18 tests). 17/18 PASS. TC-AE-13 FAILS with a **test code defect** (not a product defect). Product behavior is correct (manual verification confirmed AC-13 met: GET /api/combat/action=405, POST without auth=403).

## Fix required (2-line change, ~2 minutes)

**File**: `sites/dungeoncrawler/tests/src/Functional/ActionEconomyTest.php`
**Line**: ~317

**Current code** (broken):
```php
$this->drupalLogout();
```

**Fixed code**:
```php
$this->drupalResetSession();
```

**Why**: `#[RunTestsInSeparateProcesses]` means each test starts with a fresh anonymous session. `drupalLogout()` navigates to `/user/logout/confirm` which only renders when a user IS logged in — so it throws ElementNotFoundException when already anonymous. `drupalResetSession()` resets the session state without a web request, which is the correct approach here.

Alternatively: simply remove the call entirely — each test already starts as anonymous in this class.

## Acceptance criteria
- [ ] `ActionEconomyTest::testAnonCannotAccessMutationEndpoints` passes without ElementNotFoundException
- [ ] Full `ActionEconomyTest` suite: 18/18 PASS
- [ ] No other tests broken
- [ ] Commit hash provided in outbox

## Verification
```bash
cd /home/keithaumiller/forseti.life/sites/dungeoncrawler
./vendor/bin/phpunit tests/src/Functional/ActionEconomyTest.php --filter testAnonCannotAccessMutationEndpoints
./vendor/bin/phpunit tests/src/Functional/ActionEconomyTest.php
```
Note: if PHPUnit DB credentials fail, provide curl evidence for TC-AE-13 as fallback (same as QA did: GET /api/combat/action=405 or 403, POST without auth=403).

## After fix
- Update your outbox with commit hash
- qa-dungeoncrawler will re-run and issue APPROVE
