# Implementation Notes (Dev-owned)
# Feature: dc-cr-conditions

## Summary
EXTEND: DB layer (applyCondition/removeCondition/getCurrentRound) is complete. Gaps: no CONDITION_CATALOG constant, `tickConditions()` does not exist, `processDyingCondition()` is a TODO stub, and `RulesEngine::checkConditionRestrictions()` is a TODO stub. First slice adds the CONDITION_CATALOG constant and implements `tickConditions()` in ConditionManager.

## Impact Analysis
- `ConditionManager.php` only — no schema changes required (DB table already has `type`, `value`, `duration`, `removed_at_round`).
- `RulesEngine::checkConditionRestrictions()` stub is in RulesEngine.php — will be addressed in slice 2.
- `processDyingCondition()` is a stub in ConditionManager — implementing it touches dying/recovery rules which tie into CombatEngine turn processing. Coordinate with dc-cr-encounter-rules to avoid conflicts.

## Files / Components Touched
- `dungeoncrawler_content/src/Service/ConditionManager.php`:
  - Add `CONDITION_CATALOG` constant (array keyed by condition name: `is_valued`, `max_value`, `effects`, `end_trigger`)
  - Implement `tickConditions(int $participant_id, int $encounter_id)`: query active conditions, decrement valued conditions by 1, remove those reaching 0
  - Implement `processDyingCondition(int $participant_id, $constitution_modifier, int $encounter_id)`: roll flat DC 10 check, adjust dying value per PF2E rules, transition to `dead` at dying 4
- `dungeoncrawler_content/src/Service/RulesEngine.php` — `checkConditionRestrictions()` (slice 2): return restrictions for paralyzed, unconscious, grabbed

## Data Model / Storage Changes
- Schema updates: none (existing `combat_conditions` table is sufficient)
- Config changes: none
- Migrations: none

## First code slice
1. Add `CONDITION_CATALOG` to `ConditionManager` covering at minimum: frightened (valued, max 4), clumsy (valued, max 4), enfeebled (valued, max 4), drained (valued, max 4), stunned (valued, max 4), dying (valued, max 4), unconscious, paralyzed, grabbed, blinded, deafened, flat-footed.
2. Implement `tickConditions()` — iterate active conditions for participant, decrement `value` for valued conditions with `end_trigger=end_of_turn`, remove when value reaches 0.
3. Verify: apply frightened 2, tick twice, confirm removed.

## Security Considerations
- Input validation: condition type must exist in CONDITION_CATALOG before inserting.
- Access checks: condition application is GM or character controller only (enforced in calling layer).
- Sensitive data handling: none.

## Testing Performed
- Commands run: (pending implementation)
- Targeted scenarios:
  - Apply frightened 2 → tick → frightened 1 → tick → removed
  - Apply non-valued `paralyzed` → tick → still present (non-valued does not decrement)
  - Apply unknown condition → expect error
  - processDyingCondition: DC 10 roll success → dying -1; failure → dying +1; dying 4 → status=dead

## Rollback / Recovery
- Revert commit. No schema changes. Conditions are encounter-scoped; clearing an encounter clears conditions.

## Knowledgebase references
- `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md`
- PF2E Core Rulebook: Conditions Appendix (reference catalog for condition definitions).

## What I learned (Dev)
- (pending)

## What I'd change next time (Dev)
- (pending)
