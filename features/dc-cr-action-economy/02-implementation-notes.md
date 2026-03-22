# Implementation Notes (Dev-owned)
# Feature: dc-cr-action-economy

## Summary
EXTEND: `RulesEngine::validateActionEconomy()` is a TODO stub; `ActionProcessor` only decrements by 1 for any action cost; `reaction_available` field is not tracked. This feature adds: (1) implement `validateActionEconomy()` to enforce multi-action budgets and reaction consumption, (2) add `reaction_available` tracking to `ActionProcessor` and `CombatEncounterStore`.

## Impact Analysis
- Touches `RulesEngine.php`, `ActionProcessor.php` — both in `dungeoncrawler_content/src/Service/`.
- `CombatEncounterStore` participant schema needs `reaction_available` bool column or JSON field.
- Existing 1-action strike/stride will continue to work; 2/3-action enforcement is additive.

## Files / Components Touched
- `dungeoncrawler_content/src/Service/RulesEngine.php` — implement `validateActionEconomy($participant, $action_cost)`
- `dungeoncrawler_content/src/Service/ActionProcessor.php` — consume action cost dynamically (not hardcoded -1); add reaction guard
- `dungeoncrawler_content/src/Service/CombatEncounterStore.php` — confirm `reaction_available` column or add to participant JSON

## Data Model / Storage Changes
- Schema updates: `reaction_available` bool on participant state (DB column or JSON field — check `CombatEncounterStore` storage pattern first)
- Config changes: none
- Migrations: any existing participants must receive `reaction_available = true` default

## First code slice
1. Implement `RulesEngine::validateActionEconomy()`:
   - Read `participant['actions_remaining']` and `participant['reaction_available']`
   - If `action_cost` is `'reaction'`: check `reaction_available === true`, return error if false
   - If `action_cost` is int: check `actions_remaining >= action_cost`, return error if not
   - If `action_cost` is `'free'`: always pass
2. Update `ActionProcessor` to call `validateActionEconomy()` before executing, and decrement by the action's actual cost (not hardcoded 1).

## Security Considerations
- Input validation: action_cost values must be validated to known values (1, 2, 3, 'free', 'reaction'); reject unknown costs.
- Access checks: participant must be the active turn participant; enforced by existing CombatEngine turn order.
- Sensitive data handling: none (no PII).

## Testing Performed
- Commands run: (pending implementation)
- Targeted scenarios:
  - `turn_start` → `actions_remaining=3, reaction_available=true`
  - spend 2-action activity → `actions_remaining=1`
  - attempt another 2-action → should reject
  - spend reaction → `reaction_available=false`
  - attempt second reaction → should reject
  - spend free action → no decrement

## Rollback / Recovery
- Revert commit. No schema migration needed if `reaction_available` is stored in participant JSON (not a new DB column). DB-column path: add nullable with default true.

## Knowledgebase references
- `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md` — run `drush cr` + QA audit after module changes.

## What I learned (Dev)
- (pending)

## What I'd change next time (Dev)
- (pending)
