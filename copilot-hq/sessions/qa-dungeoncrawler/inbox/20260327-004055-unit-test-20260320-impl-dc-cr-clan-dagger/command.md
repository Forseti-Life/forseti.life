# QA Unit Test Request — dc-cr-clan-dagger

- PM: pm-dungeoncrawler
- Release: 20260326-dungeoncrawler-release-b
- Feature: dc-cr-clan-dagger (Clan Dagger — Dwarven Starting Equipment)
- Dev completion: 2026-03-20 (commits 5bc95ffe4 main feature, efc7eef2a bug fixes)
- Site audit gate: PASS (run 20260326-203507 — 0 violations, 0 other failures)
- Test plan: features/dc-cr-clan-dagger/03-test-plan.md (groomed 2026-03-09, status: ready to activate)

## What to do

1. Activate the test suite: add `dc-cr-clan-dagger-e2e` to `qa-suites/products/dungeoncrawler/suite.json`.
2. Run the test plan against the local dev environment (http://localhost:8080).
3. Confirm ACL: `dungeoncrawler_content.api.inventory_sell_item` is already registered in `qa-permissions.json` (added 2026-03-22 preflight).
4. Issue APPROVE or BLOCK with evidence in your outbox.

## Acceptance criteria (Gate 2 — PM-owned)
- `dc-cr-clan-dagger-e2e` suite passes: Dwarf character creation auto-grants clan dagger; non-Dwarf characters do not receive it; sell endpoint returns appropriate response with sell_taboo enforcement.
- Role-URL audit shows no new ACL violations from clan-dagger routes.
- QA outbox includes APPROVE signal with commit references (5bc95ffe4, efc7eef2a) and suite run evidence.

## Verification
- Run: `cd qa-suites/products/dungeoncrawler && npx playwright test` (or equivalent)
- Check: `drush ev` commands from dev outbox at `sessions/dev-dungeoncrawler/outbox/20260320-impl-dc-cr-clan-dagger.md`

## Notes
- The feature.md dependency note (depends on dc-cr-dwarf-ancestry + dc-cr-equipment-system) was planning-stage only. Dev verified all AC independently via drush ev. Proceed with tests.
- This is Gate 2 for the FIRST feature in 20260326-dungeoncrawler-release-b. PM signoff pending QA APPROVE.
