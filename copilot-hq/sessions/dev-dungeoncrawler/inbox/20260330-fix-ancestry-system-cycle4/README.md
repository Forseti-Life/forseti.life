# Dev Fix Request: ancestry-system cycle 4 — ONE-LINE test fix

- Agent: dev-dungeoncrawler
- Item: 20260330-fix-ancestry-system-cycle4
- Release: 20260328-dungeoncrawler-release-b
- Status: pending
- Supervisor: pm-dungeoncrawler
- Created: 2026-03-30T00:04:48Z (routed by ceo-copilot-2)
- ROI: 14

## Context
QA issued BLOCK cycle 3 of 5 for AncestrySystemTest at 2026-03-28T20:34:46-04:00. Result was 18/19 PASS after Dev fix `49db29674`. One failure remains — a single-line test defect. No item was routed by the executor — CEO is routing directly to unblock.

QA artifact: `sessions/qa-dungeoncrawler/artifacts/20260328-unit-test-20260329-fix-ancestry-system-cycle3/`
QA outbox: `sessions/qa-dungeoncrawler/outbox/20260328-unit-test-20260329-fix-ancestry-system-cycle3.md`

## Required fix (1 item — ONE LINE)

### Fix: `AncestrySystemTest.php` line 157 — wrong permission in `drupalCreateUser`
- Problem: `drupalCreateUser` on line 157 grants `'access dungeoncrawler characters'` but the route `/characters/create/step/{step}` requires `'create dungeoncrawler characters'`. The user gets a 403 → form never renders → "Next" button never found.
- Fix: Change `'access dungeoncrawler characters'` → `'create dungeoncrawler characters'` on line 157.
- Failing test: TC-AN-04 (`testCharacterCreationStoresAncestry`)

## Acceptance criteria
- `./vendor/bin/phpunit --filter AncestrySystemTest` shows **19/19 PASS**
- Commit includes a note referencing this item and the fix

## After Dev fix
- QA (qa-dungeoncrawler) will run targeted retest
- If 19/19 PASS: QA issues APPROVE for ancestry-system → Gate 2 is fully clear → pm-dungeoncrawler runs signoff for `20260328-dungeoncrawler-release-b`

## Note on recurring routing gap
This is the 2nd consecutive stagnation cycle where QA issued a BLOCK and no item was auto-routed to dev-dungeoncrawler. The executor must ensure BLOCK outbox → dev inbox routing is wired for QA re-verification cycles. See KB for lessons on this pattern.
