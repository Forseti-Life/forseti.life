# Feature Brief: Cleric Class Mechanics

- Work item id: dc-cr-class-cleric
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
- DB sections: core/ch03/Cleric
- Depends on: dc-cr-character-class, dc-cr-spellcasting

## Goal

Implement the Cleric class with Doctrine (Cloistered Cleric vs Warpriest), Divine Font (Heal or Harm charges based on deity alignment, quantity = 1 + CHA mod), prepared divine spellcasting, domain spells, and deity anathema enforcement.

## Source reference

> "Clerics are the instruments of their deities, combining magic with devotion." (Chapter 3: Classes, PF2E Core Rulebook)

## Implementation hint

Character entity gains `cleric_doctrine` enum and `deity_id` FK. `DivineFont::calculateCharges(character)` = 1 + CHA modifier; Heal if deity is good/neutral, Harm if deity is evil. Domain Spells: character has `domain_spells[]` (2 domains from deity's list), each grants a focus spell; `focus_point_pool` = number of domain feats taken (max 3). Prepared casting: `prepared_spells[level][slot]` array updated at daily prep. Warpriest: martial proficiency + medium armor; Cloistered: expert spell DC + divine breadth. Anathema: check triggers on certain actions; if anathema violated, character loses Divine Font charges until atoned.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; divine font and domain actions require character ownership (`_character_access: TRUE`)
- CSRF expectations: all POST/PATCH class/cleric routes require `_csrf_request_header_mode: TRUE`
- Input validation: doctrine enum and deity FK validated server-side; divine font type (heal/harm) computed server-side from deity alignment, not client-supplied
- PII/logging constraints: no PII logged; character id + spell id + target id + action type only

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
