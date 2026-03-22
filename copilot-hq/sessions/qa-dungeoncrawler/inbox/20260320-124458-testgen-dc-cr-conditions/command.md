# Test Plan Design: dc-cr-conditions

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-03-20T12:44:58-04:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-conditions/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-conditions "<brief summary>"
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
# Feature: dc-cr-conditions

## Gap analysis reference

Gap analysis performed against `dungeoncrawler_content/src/Service/ConditionManager.php`, `combat_conditions` DB schema.

Coverage findings:
- `applyCondition()` — DB insert with type, value, duration, source — **Full**
- `removeCondition()` — DB update with removed_at_round — **Full**
- `getCurrentRound()` — reads active encounter round — **Full**
- Valued conditions (frightened 1–4, enfeebled 1–4, etc.) — **Partial** (value field exists in DB; enforcement of value-based effects on stat calculations not confirmed)
- Full PF2E condition catalog (all ~40 conditions) — **Partial** (schema has type string; content definitions/effects not found as data)
- End-of-turn automatic condition decrement — **None** (no tick/advance logic found in ConditionManager)
- Dying/recovery rules — **None** (no `processDying()` method found)
- `RulesEngine::checkConditionRestrictions()` — **None** (method exists but body is TODO stub)

Feature type: **enhancement** (DB layer complete; add condition catalog, effect enforcement, end-of-turn tick, dying rules)

## Happy Path
- [ ] `[NEW]` A condition catalog constant/config defines all PF2E conditions with: name, is_valued (bool), max_value, effects (stat modifiers, action restrictions), and end_trigger (end_of_turn / save / action / persistent).
- [ ] `[EXTEND]` `applyCondition()` validates that the condition type exists in the catalog before inserting.
- [ ] `[NEW]` `tickConditions(int $participant_id, int $encounter_id)` decrements valued conditions by 1 at end of that participant's turn (e.g., frightened 2 → frightened 1 → removed).
- [ ] `[NEW]` `processDying(int $participant_id, int $encounter_id)` implements the dying/recovery rules: at start of turn, participant rolls a flat DC 10 check; success reduces dying value by 1; critical success reduces by 2 and removes dying; failure increases by 1; critical failure increases by 2 (death at dying 4).
- [ ] `[EXTEND]` `RulesEngine::checkConditionRestrictions()` returns a correct restriction for paralyzed (cannot act), unconscious (cannot act), and grabbed (cannot move) conditions.
- [ ] `[TEST-ONLY]` Conditions that impose a penalty to a stat (e.g., clumsy −2 to Dex-based checks) are reflected in the character's effective stat calculation.

## Edge Cases
- [ ] `[EXTEND]` Applying a valued condition that is already present increases the value (not creates a duplicate), capped at the condition's max_value.
- [ ] `[NEW]` Applying a non-valued condition that is already present is a no-op (idempotent).
- [ ] `[NEW]` `dying 4` transitions participant to `dead` status and ends their participation in the encounter.
- [ ] `[EXTEND]` Removing a condition that does not exist on the participant returns a no-op (not an error).

## Failure Modes
- [ ] `[TEST-ONLY]` Error messages are clear and actionable.
- [ ] `[NEW]` Applying an unknown condition type returns an explicit error.

## Permissions / Access Control
- [ ] Anonymous user behavior: condition state is readable (displayed to all encounter participants).
- [ ] Authenticated user behavior: only GM or the character's controller may apply conditions.
- [ ] Admin behavior: admin can remove any condition for moderation.

## Data Integrity
- [ ] `combat_conditions` table uses insert-only for apply and soft-delete (removed_at_round) for remove — no hard deletes during an active encounter.
- [ ] Rollback path: conditions are encounter-scoped; clearing an encounter clears its conditions.

## Knowledgebase check
- Related lessons/playbooks: none found. Reference PF2E Core Rulebook Conditions Appendix for the full catalog.

## Test path guidance (for QA)
| Requirement | Test path |
|---|---|
| Condition catalog validation | `tests/src/Unit/Service/ConditionManagerTest.php` |
| `tickConditions` decrement | `tests/src/Unit/Service/ConditionManagerTest.php` (extend) |
| `processDying` recovery loop | `tests/src/Unit/Service/ConditionManagerTest.php` (extend) |
| `checkConditionRestrictions` | `tests/src/Unit/Service/RulesEngineTest.php` (extend) |
| Valued condition stacking | `tests/src/Unit/Service/ConditionManagerTest.php` (extend) |
