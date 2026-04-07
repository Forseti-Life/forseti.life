# Feature Brief: Sorcerer Class Mechanics

- Work item id: dc-cr-class-sorcerer
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch03
- Category: class
- Created: 2026-04-07
- DB sections: core/ch03/Sorcerer
- Depends on: dc-cr-character-class, dc-cr-spellcasting

## Goal

Implement the Sorcerer class with bloodline selection (determines spell tradition and list), spontaneous casting with spell slots, signature spells (heighten freely), bloodline powers as focus spells, and bloodline spell additions.

## Source reference

> "Sorcerers are spellcasters who use their innate understanding of magic to gain incredible power." (Chapter 3: Classes, PF2E Core Rulebook)

## Implementation hint

Character entity gains `sorcerer_bloodline` enum; bloodline determines `spell_tradition` (arcane/divine/occult/primal). `SpellRepertoire` = known spells list; `spell_slots[level]` = spontaneous slots. Bloodline Spells: specific spells automatically added to repertoire at fixed levels. Bloodline Powers: focus spells from bloodline; `focus_point_pool` = number of bloodline power feats. Signature Spells: player selects one spell per spell level; these can be heightened freely when cast. `SpontaneousCaster::castSpell(character, spell_id, slot_level)` checks slot availability and signature status.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; sorcerer casting requires character ownership (`_character_access: TRUE`)
- CSRF expectations: all POST/PATCH class/sorcerer routes require `_csrf_request_header_mode: TRUE`
- Input validation: bloodline enum validated server-side; slot level validated against available spontaneous slots; signature spell heightening validated server-side
- PII/logging constraints: no PII logged; character id + spell id + slot level + bloodline id only

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
