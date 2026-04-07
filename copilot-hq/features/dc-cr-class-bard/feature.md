# Feature Brief: Bard Class Mechanics

- Work item id: dc-cr-class-bard
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
- DB sections: core/ch03/Bard
- Depends on: dc-cr-character-class, dc-cr-spellcasting, dc-cr-focus-spells

## Goal

Implement the Bard class with Muse (Enigma/Maestro/Polymath), Composition Spells as occult cantrips (Inspire Courage, Inspire Heroics, Inspire Defense), spontaneous occult spellcasting, signature spells, and occult breadth.

## Source reference

> "Bards tap into the power of occult magic, and their performances echo with supernatural force." (Chapter 3: Classes, PF2E Core Rulebook)

## Implementation hint

Muse stored as `bard_muse` enum. Composition spells are a special category: cost 1 action, function as cantrips (auto-heighten to half level), not held in memory slots; add to `character.composition_spells[]`. Inspire Courage/Heroics/Defense: create an aura condition on all allies in 60 ft; `BardAuraManager::applyCompositionEffect(character, spell_id, encounter_participants)`. Spontaneous casting: `spell_repertoire[]` list + `spell_slots[by_level]` array; Signature Spells can be heightened freely. Occult Breadth: +1 spell slot of each level.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; composition spell targeting requires encounter ownership
- CSRF expectations: all POST/PATCH class/bard routes require `_csrf_request_header_mode: TRUE`
- Input validation: muse enum validated server-side; composition spell ids validated against bard spell list; aura target ids validated as encounter participants
- PII/logging constraints: no PII logged; character id + spell id + target ids only

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
