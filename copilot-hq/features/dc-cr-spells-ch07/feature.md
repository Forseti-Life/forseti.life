# Feature Brief: Core Book Chapter 7 — Spellcasting Rules

- Work item id: dc-cr-spells-ch07
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: in_progress
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch07
- Category: spells
- Created: 2026-04-07
- DB sections: core/ch07/Arcane Spell List (Summary), core/ch07/Casting Spells, core/ch07/Divine Spell List (Summary), core/ch07/Focus Spells by Class, core/ch07/Occult Spell List (Summary), core/ch07/Primal Spell List (Summary), core/ch07/Special Spell Types, core/ch07/Spell Slots and Spellcasting Types, core/ch07/Spell Stat Block Format, core/ch07/Traditions and Schools
- Depends on: dc-cr-spellcasting, dc-cr-focus-spells, dc-cr-rituals

## Goal

Implement the spell catalog content type for all Chapter 7 spells, with fields for tradition, level, cast time, components, range, area, targets, duration, save, and heightened effects, plus the cantrip auto-heightening rule.

## Source reference

> "Spells make up a large part of the game, providing a wealth of options for adventurers." (Chapter 7: Spells, PF2E Core Rulebook)

## Implementation hint

Spell entity: `name`, `level` (1–10, 0 for cantrip), `traditions[]` (arcane/divine/occult/primal), `cast_time` (1_action/2_action/reaction/1_minute/etc), `components[]` (verbal/somatic/material/focus), `range`, `area`, `targets`, `duration`, `save_type` (basic/fortitude/reflex/will/none), `effect_text`, `heightened_effects[level → delta_text]`. Cantrips: `is_cantrip = true`; `SpellResolver::getEffectiveLevel(spell, caster_level)` returns `ceil(caster_level / 2)`. `SpellCastingService::cast(character, spell_id, slot_level, targets)` routes to `SpellResolver` which applies heightening, resolves saves, applies conditions.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; spell casting requires character ownership and valid encounter context
- CSRF expectations: all POST/PATCH spell-casting routes require `_csrf_request_header_mode: TRUE`
- Input validation: spell id validated against spell catalog; slot level validated against available spell slots; target ids validated as encounter participants; save type resolved server-side from spell entity
- PII/logging constraints: no PII logged; character id + spell id + slot level + target ids + outcome only

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
