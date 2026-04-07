# Feature Brief: DC Rarity and Spell-Level Adjustments

- Website: dungeoncrawler
- Type: extend
- Module: dungeoncrawler_content
- Priority: P1
- Status: in_progress
- Release: none
- Dependencies: dc-cr-encounter-rules, dc-cr-spellcasting

## Goal

Implement the DC adjustment modifiers for rarity (Common +0, Uncommon +2, Rare +5, Unique +10) and spell level (same-rank +0, 1 higher +2, 2 higher +5) as additive modifiers applied on top of base DC-by-level tables.

## Source reference

> "Some tasks are harder or easier based on their rarity or the level of the spell involved." (Chapter 10: Game Mastering, PF2E Core Rulebook)

## Implementation hint

`DCModifierService::getRarityAdjustment(rarity)` returns enum→integer map: common=0, uncommon=2, rare=5, unique=10. `DCModifierService::getSpellRankAdjustment(spell_level, caster_level)` returns delta based on level difference table. These are composed in `DCCalculator::compute(base_dc, rarity, spell_level_delta)` = base_dc + rarity_adj + spell_level_adj. Remove any hardcoded TASK_DC constants from codebase; replace with `DCCalculator.compute()` calls passing server-authoritative base DC from `DCByLevelTable::get(level)`.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: DC computation is server-side only; no client-supplied DC modifiers accepted
- CSRF expectations: DC lookup endpoints are GET-only; no DC override POST routes exposed
- Input validation: rarity enum validated server-side; spell level delta computed from entity data, not client input; all DC values returned from server tables
- PII/logging constraints: no PII logged; DC lookups are ephemeral computation results, not stored

## Roadmap section
- Book: core, Chapter: ch10
- REQs: 2320, 2321, 2322, 2328
- See `runbooks/roadmap-audit.md` for audit process.
