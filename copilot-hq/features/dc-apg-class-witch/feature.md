# Feature Brief: Witch Class Mechanics (APG)

- Work item id: dc-apg-class-witch
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: in_progress
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Advanced Player's Guide, Chapter 2 (Witch)
- Category: game-mechanic
- Created: 2026-04-07
- DB sections: apg/ch02/Witch
- Depends on: dc-cr-character-class, dc-cr-character-leveling, dc-cr-spellcasting, dc-cr-focus-spells

## Goal

Implement the Witch class with Patron (determines spell list and Hex Cantrips), mandatory Familiar (delivers touch spells), Lesson system (gain new hexes), and prepared spellcasting where spells are learned through the Familiar's teachings.

## Source reference

> "Witches learn magic from a mysterious patron, channeling their power through a familiar." (Advanced Player's Guide, Witch Class)

## Implementation hint

Character entity gains `witch_patron` enum (determines `spell_tradition` and starting Hex Cantrips). Familiar is mandatory: `character.familiar_id` FK set at class selection. Hex Cantrips are focus spells from patron; `HexManager::cast(character, hex_id)` costs 1 Focus Point. Lesson system: witches gain Lessons as class feats; each grants a new Hex (focus spell). Prepared casting: spells known added to character spellbook via familiar teaching (narrative) — mechanically same as Wizard's prepared casting but uses `witch_spellbook`. Touch spell delivery: `FamiliarDelivery::deliver(character, spell_id, target_id)` allows familiar to deliver touch spell in familiar's turn.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; Witch class actions and familiar delivery require character ownership (`_character_access: TRUE`)
- CSRF expectations: all POST/PATCH class/witch routes require `_csrf_request_header_mode: TRUE`
- Input validation: patron enum validated server-side; Hex ids validated against patron's allowed hexes; familiar delivery target validated as encounter participant
- PII/logging constraints: no PII logged; character id + hex id + familiar id + target id only

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
