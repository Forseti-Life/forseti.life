# Outbox: dc-cr-action-economy implementation

- Status: done
- Summary: Implemented the dc-cr-action-economy feature for dungeoncrawler. The core validation logic (`validateActionEconomy`, `startTurn` reset, `reaction_available` DB storage) was already present in `RulesEngine.php` and `CombatEngine.php`. Gaps filled: added `reaction` and `free_action` dispatch cases to `ActionProcessor.executeAction()`, added `executeReactionAction()` and `executeFreeAction()` methods, fixed `executeStrike`/`executeStride` to use `$economy['actions_after']` instead of hardcoded `-1`, and created `ActionEconomyTest.php` with all 18 PHPUnit test cases (TC-AE-01..18). All 136 existing drush tests pass with no regressions. Stage-0 confirmations documented below.

## Next actions
- QA handoff: run `./vendor/bin/phpunit -c phpunit.xml.dist --testsuite=functional` (ActionEconomyTest 18 cases) and `scripts/site-audit-run.sh dungeoncrawler` (role-url-audit)
- QA to confirm `qa-permissions.json` rules for `/api/combat/action` (TC-AE-13/14) — route requires `access dungeoncrawler characters`
- 30 QA failures from `20260327-110142-qa-findings-dungeoncrawler-30` still need separate triage

## Blockers
- None

## Stage-0 confirmations (from test plan)

1. **Routes**: `dungeoncrawler_content.routing.yml` already registers `/api/combat/action` (POST, requires `access dungeoncrawler characters`) and `/api/combat/start-turn` (POST). No schema changes to routing needed.

2. **Migration hook**: No schema changes were made to `combat_participants` table. The `reaction_available` column already existed as `INT(1)` with default 1. No migration hook is required; existing participant data is unaffected.

3. **Module uninstall**: No new database tables or schema introduced. Existing `hook_uninstall` in `dungeoncrawler_content.install` removes all module-owned tables cleanly. Uninstall path confirmed clean.

## Commits
- `fd4854e94` — feat(dc-cr-action-economy): implement reaction/free_action dispatch + ActionEconomyTest (TC-AE-01..18)
- `f66b85f27` — docs(dc-cr-action-economy): update implementation notes with testing evidence

## Rollback steps
1. `git revert fd4854e94 --no-edit` — reverts ActionProcessor dispatch + test file
2. `git revert f66b85f27 --no-edit` — reverts implementation notes update
3. `drush cr` — clear caches
4. No DB migration needed (no schema changes).

## ROI estimate
- ROI: 20
- Rationale: Action economy is the P0 foundation for all encounter-mode gameplay. Without it, class features, spells, and skill actions with varying action costs cannot be implemented. Completing this unblocks all downstream encounter feature work.
