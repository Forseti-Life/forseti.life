# Implementation Notes (Dev-owned)
# Feature: dc-cr-encounter-rules

## Summary
EXTEND: Encounter lifecycle, round management, and MAP calculation are complete. Gaps: no `resolveAttack()` method in CombatEngine, initiative auto-roll uses injected custom values only (no Perception-based default), and end-of-turn condition tick is not wired. First slice implements `resolveAttack()` in CombatEngine and adds Perception-based auto-initiative to `startEncounter()`.

## Impact Analysis
- `CombatEngine.php` — new `resolveAttack()` method; extends `startEncounter()` for auto-roll.
- `CombatCalculator.php` — MAP calculation already exists; `resolveAttack()` calls it.
- `HPManager.php` — `applyDamage()` may need damage type/resistance extension (slice 2).
- `ConditionManager` — end-of-turn tick called from `CombatEngine::endTurn()` after resolveAttack (slice 2, coordinate with dc-cr-conditions).
- Must read `docs/dungeoncrawler/issues/issue-4-combat-encounter-system-design.md` before implementing `resolveAttack()` (per AC KB note).

## Files / Components Touched
- `dungeoncrawler_content/src/Service/CombatEngine.php`:
  - `startEncounter()` — add auto-roll for participants without custom initiative: d20 + Perception modifier
  - New method `resolveAttack(int $participant_id, int $target_id, mixed $weapon_id, int $encounter_id): array` — returns `{roll, total, degree_of_success, map_applied}`
- `dungeoncrawler_content/src/Service/HPManager.php` — `applyDamage()` with damage type awareness (slice 2)

## Data Model / Storage Changes
- Schema updates: none required for resolveAttack (reads existing participant/encounter state)
- Config changes: none
- Migrations: none

## First code slice
1. Read `docs/dungeoncrawler/issues/issue-4-combat-encounter-system-design.md`.
2. Implement `resolveAttack()`:
   - Roll d20 + attacker's attack bonus
   - Get MAP from `CombatCalculator::calculateMultipleAttackPenalty(attack_count, is_agile)`
   - Apply MAP to total
   - Compare vs. target AC → degree of success: critical success (total >= AC+10), success, failure, critical failure (total <= AC-10)
   - Natural 20 bumps degree up; natural 1 bumps down
   - Return structured array
3. Extend `startEncounter()`: for participants with no custom initiative, roll d20 + perception_modifier; sort descending.

## Security Considerations
- Input validation: participant_id and target_id must exist in encounter before resolving attack.
- Access checks: only character's controlling user or GM may call resolveAttack.
- Sensitive data handling: none.

## Testing Performed
- Commands run: (pending implementation)
- Targeted scenarios:
  - resolveAttack: roll=20 (natural) → critical success regardless of AC
  - resolveAttack: roll=1 (natural) → critical failure regardless of AC
  - MAP: second attack penalty = -5 (normal) or -4 (agile)
  - Auto-initiative: participant with no custom initiative gets Perception-based roll

## Rollback / Recovery
- Revert commit. Encounter state is DB-stored; no persistent side effects from method addition.

## Knowledgebase references
- `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md`
- `docs/dungeoncrawler/issues/issue-4-combat-encounter-system-design.md` — read before implementing resolveAttack.

## What I learned (Dev)
- (pending)

## What I'd change next time (Dev)
- (pending)
