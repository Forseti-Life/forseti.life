# Test Plan Design: dc-cr-action-economy

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-03-27T01:59:20-04:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-action-economy/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-action-economy "<brief summary>"
   ```
   This marks the feature groomed/ready and notifies PM — do not skip this step.

### DO NOT do during grooming

- Do NOT edit `qa-suites/products/dungeoncrawler/suite.json`
- Do NOT edit `org-chart/sites/dungeoncrawler.life/qa-permissions.json`
Those changes happen at Stage 0 of the next release when this feature is selected into scope.

### Test case mapping guide (for 03-test-plan.md)

| AC type | Test approach (write in plan, activate at Stage 0) |
|---------|---------------------------------------------------|
| Route accessible to role X | `role-url-audit` suite entry — HTTP 200 for role X |
| Route blocked for role Y | `role-url-audit` suite entry — HTTP 403 for role Y |
| Form / E2E user flow | Playwright suite — new test or extend existing |
| Content visible / not visible | Crawl + role audit entry |
| Permission check | `qa-permissions.json` rule + role audit entry |

See full process: `runbooks/intake-to-qa-handoff.md`

## Acceptance Criteria (attached below)

# Acceptance Criteria (PM-owned)

## Gap analysis reference

Gap analysis performed against `dungeoncrawler_content/src/Service/ActionProcessor.php`, `RulesEngine.php`, `CombatEncounterStore.php`.

Coverage findings:
- `ActionProcessor::executeAction()` — dispatches to stride/strike; checks `actions_remaining >= 1` before Strike — **Partial** (1-action enforcement exists; 2-action/3-action activity cost enforcement not found)
- `actions_remaining` field on participants (stored in DB via `CombatEncounterStore`) — **Partial** (field referenced; reset logic at turn start needs verification)
- `reaction_available` field — **None** (not found in ActionProcessor or CombatEncounterStore)
- `RulesEngine::validateActionEconomy()` — method exists but body is a TODO stub — **Partial** (stub only)
- Free action / reaction action types — **None** (only stride/strike dispatched in ActionProcessor)

Feature type: **enhancement** — action budget enforcement for 1-action Strike exists; complete 2/3-action activities, reaction tracking, and free actions.

All criteria below are tagged accordingly:

## Happy Path
- [ ] `[EXTEND]` A character participant has an action budget of 3 actions and 1 reaction at the start of their turn.
- [ ] `[EXTEND]` Each turn, `actions_remaining` resets to 3 and `reaction_available` resets to true.
- [ ] `[EXTEND]` Actions have a defined cost: 1-action, 2-action (activity), 3-action (activity), free action, or reaction.
- [ ] `[EXTEND]` Spending an action decrements `actions_remaining` by the action's cost (1, 2, or 3). Free actions do not decrement it.
- [ ] `[NEW]` Spending a reaction sets `reaction_available` to false.
- [ ] `[EXTEND]` A character cannot take a paid action if `actions_remaining` is less than the action's cost.
- [ ] `[NEW]` A character cannot use a reaction if `reaction_available` is false.

## Edge Cases
- [ ] `[EXTEND]` A 2-action activity attempted when only 1 action remains is rejected with an explicit message ("Not enough actions").
- [ ] `[NEW]` A reaction attempted when already spent is rejected ("Reaction already used this turn").
- [ ] `[EXTEND]` Free actions are always available (do not consume action budget) and can be taken even at 0 remaining actions.
- [ ] `[EXTEND]` `actions_remaining` cannot go below 0 (guard against double-decrements).

## Failure Modes
- [ ] `[EXTEND]` Invalid action cost values (e.g., 0, negative, or >3) are rejected at content-type validation with a clear error.
- [ ] `[EXTEND]` Attempting to spend actions out of turn (no active turn state) returns an appropriate error.

## Permissions / Access Control
- [ ] Anonymous user behavior: turn/action state is not directly exposed to anonymous users; read-only game state views (if any) may be public but mutation endpoints require authentication.
- [ ] Authenticated user behavior: players may spend their own character's actions; cannot spend another character's actions.
- [ ] Admin behavior: admins may reset or override turn state for GM tooling purposes.

## Data Integrity
- [ ] No data loss on update/migration: any existing character nodes must receive valid default action state on module install/update.
- [ ] Rollback path: disabling the action-economy sub-module must not corrupt existing character nodes; state fields may be left empty/null.

## Verification method
- `drush php-eval` or a custom test script to create a test character, simulate turn start, spend actions, verify state:
  ```
  drush php-eval "/* create character, call turn_start(), assert actions_remaining=3, spend 2 actions, assert actions_remaining=1 */"
  ```
- QA automated audit must still pass (0 violations, 0 failures) after module changes are deployed to local.

## Knowledgebase check
- `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md` — reminder to run `drush cr` + QA audit immediately after module changes to surface regressions quickly.
- KB: none found for PF2E action economy specifically; this is the first feature in the pipeline.
