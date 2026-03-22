# Acceptance Criteria — dc-cr-character-leveling

- Feature: Character Leveling and Advancement
- Release target: 20260308-dungeoncrawler-release-b
- PM owner: pm-dungeoncrawler
- Date groomed: 2026-03-08

## Scope

Implement the level-up flow that advances a character from their current level to the next (levels 1–20), applying new class features, ability boosts (at levels 5/10/15/20), additional skill increases, and new feats.

## Prerequisites satisfied

- dc-cr-character-creation: complete
- dc-cr-character-class: complete (advancement tables exist)
- dc-cr-background-system: complete (skill proficiencies established at creation)

## Knowledgebase check

None found for character leveling specifically. See `COMBAT_ENGINE_ARCHITECTURE.md` for character entity model. Note: dc-cr-xp-rewards dependency is removed — leveling gates on session milestone (not XP threshold) per prior PM decision.

## Happy Path

- [ ] `[NEW]` A character at level N can trigger a level-up to level N+1 when they have reached the session milestone trigger.
- [ ] `[NEW]` Level-up presents the character's class advancement table for level N+1 — showing which class features, feats, and ability boosts are available at that level.
- [ ] `[NEW]` Class features granted at the new level are applied automatically (no player choice required unless the feature requires a selection, e.g., a feat slot).
- [ ] `[NEW]` Ability boosts at levels 5, 10, 15, and 20: player selects which abilities to boost (four boosts at each milestone); each ability may be boosted at most once per milestone; choices are persisted.
- [ ] `[NEW]` Skill increases at appropriate levels (per class advancement table) let the player raise one skill proficiency rank by one step.
- [ ] `[NEW]` Feat slots at the new level (class feat, skill feat, general feat, ancestry feat as applicable) display the eligible feat catalog filtered by prerequisites; player selects and choice is persisted.
- [ ] `[NEW]` Level-up cannot be re-triggered until the next milestone; the endpoint is idempotent for the same level transition.
- [ ] `[NEW]` Completed level-up persists across save/reload without data loss (character level, features, boosts, skill ranks all survive session restart).

## Edge Cases

- [ ] `[NEW]` Attempting to level up past level 20 is rejected with a clear message ("Already at maximum level").
- [ ] `[NEW]` Attempting to level up without reaching the session milestone is rejected with a clear message.
- [ ] `[NEW]` Skipping levels (e.g., level 1 → 3) is not permitted; each level must be applied in sequence.
- [ ] `[NEW]` A class feature that has no player-choice component (e.g., "gain +10 HP") is auto-applied without prompting.
- [ ] `[NEW]` An ability boost that would exceed the stat cap (18 at character creation; per rule, boosts are capped at 18 unless the ability is already 18+) is correctly handled.

## Failure Modes

- [ ] `[NEW]` Invalid character ID or non-existent character returns a structured error.
- [ ] `[NEW]` Concurrent level-up requests for the same character are serialized or rejected (no partial double-level-up).
- [ ] `[NEW]` Missing class advancement data for a given level returns an actionable error, not a PHP exception.

## Permissions / Access Control

- [ ] Only the character's controlling player (or GM) may trigger a level-up.
- [ ] Anonymous users cannot trigger or view level-up state (session-scoped).
- [ ] Admin may force-apply a level or reset level-up state for GM tooling purposes.

## Gameplay-rule alignment

- PF2e Chapter 2: character advancement tables are the authoritative source.
- Ability boost caps: no ability may exceed 18 via boosts at character creation; subsequent boosts at levels 5/10/15/20 may push past 18 per PF2e rules (the cap only applies at creation).
- Feat prerequisites: must be validated at selection time (not just at display time).

## Test path guidance (for QA)

| Requirement | Test path |
|---|---|
| Level-up trigger | Set character to milestone; call level-up API; verify level incremented |
| Class feature auto-apply | Level up fighter to level 3 (Shield Block); verify ability in character state |
| Ability boost selection | Trigger level 5; submit boost choices; verify stats updated |
| Skill increase | Trigger applicable level; submit skill choice; verify proficiency rank incremented |
| Feat selection | Trigger level with feat slot; submit feat; verify feat in character abilities |
| Already max level | Attempt level-up on level 20 char; verify rejection |
| Save/reload persistence | Level up; restart session; verify all changes survived |
