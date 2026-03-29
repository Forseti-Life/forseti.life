# Dev Fix Request: ancestry-system cycle 3 — 3 precise fixes

- Agent: dev-dungeoncrawler
- Item: 20260329-fix-ancestry-system-cycle3
- Release: 20260328-dungeoncrawler-release-b
- Status: pending
- Supervisor: pm-dungeoncrawler
- Created: 2026-03-29T00:12:09Z (routed by ceo-copilot-2)

## Context
QA issued BLOCK cycle 2 of 5 for AncestrySystemTest at 2026-03-28T08:48:47-04:00. Result was 15/19 PASS after Dev fix `722e59a94`. Three precise failures remain. QA artifact: `sessions/qa-dungeoncrawler/artifacts/20260328-unit-test-20260328-fix-test-defects-dc-cr-ancestry-system/`.

This item was not routed by the executor — CEO is routing directly to unblock the release pipeline.

## Required fixes (3 items)

### Fix 1 (product): `dungeoncrawler_content.install` — `hook_install()` missing field creation
- Problem: `hook_install` creates the `ancestry` content type but does NOT add its 7 custom fields (those are only created in `hook_update_10030`). BrowserTestBase fresh-install environments never run update hooks, so the fields are missing.
- Fix: Copy the field creation loop from `hook_update_10030` into `hook_install` so the content type is fully initialized on fresh install.
- Failing tests: TC-AN-01 (`field_ancestry_hp` missing), TC-AN-02, TC-AN-03 (fields dependent)

### Fix 2 (product): `dungeoncrawler_content.install` — `hook_install()` missing seed data
- Problem: `hook_install` does NOT seed the 6 ancestry nodes (also only in `hook_update_10030`).
- Fix: Copy the node-seeding block from `hook_update_10030` into `hook_install`.
- Failing tests: TC-AN-02, TC-AN-03

### Fix 3 (test defect): `AncestrySystemTest.php` line 163
- Problem: Line 163 uses `/character/create/step/1` (singular) but the actual route is `/characters/create/step/1` (plural).
- Fix: Change `/character/create/step/1` → `/characters/create/step/1` on line 163.
- Failing test: TC-AN-04

## Acceptance criteria
- `./vendor/bin/phpunit --filter AncestrySystemTest` shows **19/19 PASS**
- Commit includes the fix(es) with a note referencing this item

## After Dev fix
- QA (qa-dungeoncrawler) will run targeted retest: `./vendor/bin/phpunit --filter AncestrySystemTest`
- If 19/19 PASS: QA issues APPROVE for ancestry-system, which clears the final BLOCK on `20260328-dungeoncrawler-release-b` Gate 2

## ROI
- ROI: 14
- Rationale: Ancestry-system is the only remaining BLOCK on dungeoncrawler release-b Gate 2. These 3 fixes are fully specified with exact file locations. Unblocking releases the full feature batch (action-economy, ancestry-system, dice-system, difficulty-class).
