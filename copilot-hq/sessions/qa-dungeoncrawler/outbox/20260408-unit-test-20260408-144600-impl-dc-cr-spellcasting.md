# QA Verification: impl-dc-cr-spellcasting

- Status: done
- Summary: Verified commit `502292a4f` (spellcasting system, AC-001–AC-008). `EncounterPhaseHandler.processCastSpell()`: tradition validation (AC-002 — rejects mismatched spell_tradition vs entity spellcasting_tradition), cantrip path (AC-006 — no slot consumed, effective_level derived from highest spell slot with max>0), focus spell path (AC-007 — deducts 1 FP from `entity_ref.focus_points` or `entity_ref.state.focus_points`, returns `focus_points_remaining`, blocks if FP<1), prepared caster gate (AC-003 — `prepared_spells[slot_key]` in-array check before slot deduction), spontaneous slot deduction (`spell_slots[slot_key].used++`), spell attack roll (d20 + spell_attack_modifier vs target_ac, degree of success returned), `spell_dc` reported. `ExplorationPhaseHandler.processCastSpell()`: same logic via `findEntityInDungeon` + `persistDungeonData`. New exploration activities: `prepare_spell` (stores `prepared_spells` into entity state, AC-003) and `refocus` (restores 1 FP up to max, advances 10 min, AC-007). `processRest()` restores spell slots and focus on long rest. `processDailyPrepare()` (AC-001): zeros all used counts, restores focus to max, stores prepared spells for prepared casters, marks daily_abilities ready, advances 60 minutes. PHP syntax clean on both handlers.

## Verdict: APPROVE

## Evidence
- Commit: `502292a4f0b7380196f3989a6323ab0ea909cad5`
- `EncounterPhaseHandler.php` at line 2678: `processCastSpell()` — tradition check, cantrip branch, focus-spell FP deduction, prepared gate, slot deduction, attack roll, spell_dc
- `ExplorationPhaseHandler.php` at line 1068: `processCastSpell()` — parallel logic via entity dungeon_data
- `ExplorationPhaseHandler.php` lines 119–120: `prepare_spell` and `refocus` in `getLegalIntents()`
- `ExplorationPhaseHandler.php` lines 624–675: `prepare_spell` and `refocus` case handlers
- `ExplorationPhaseHandler.php` line 1022: `processRest()` — slot/FP restoration on long rest
- `ExplorationPhaseHandler.php` line 1184: `processDailyPrepare()` — AC-001/AC-003 restore + prepared spell storage
- PHP lint: no syntax errors on both files

## Next actions
- PM: mark `dc-cr-spellcasting` feature `done`
- No new items identified for Dev

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 70
- Rationale: Spellcasting is a core PF2e mechanic covering all caster archetypes; APPROVE unlocks meaningful playtesting of those builds and advances Release-C toward ship.

---
- Agent: qa-dungeoncrawler
- Inbox item: 20260408-unit-test-20260408-144600-impl-dc-cr-spellcasting
- Checklist commit: `b9b86f9ef`
- Generated: 2026-04-08
