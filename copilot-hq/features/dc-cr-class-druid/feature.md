# Feature Brief: Druid Class Mechanics

- Work item id: dc-cr-class-druid
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
- DB sections: core/ch03/Druid
- Depends on: dc-cr-character-class, dc-cr-spellcasting, dc-cr-animal-companion

## Goal

Implement the Druid class with Order (Animal/Leaf/Storm/Wild), primal prepared spellcasting, Wild Shape (polymorph form shifts), order spells, druidic language, and wild empathy.

## Source reference

> "Druids call upon the living magic of plants, animals, and the physical world to cast spells." (Chapter 3: Classes, PF2E Core Rulebook)

## Implementation hint

Character entity gains `druid_order` enum. Wild Shape: `WildShapeManager::transform(character, form_id)` validates form availability (level-gated), applies form stat block overlay (size/speed/attack changes), stores `active_form_id`. Order Animal: grants `animal_companion_id` FK at L1. Order Wild: Wild Shape from L1; unlocks more powerful forms earlier. Order Storm: bonus storm spells + electrical/sonic immunity at higher levels. Order Leaf: familiar at L1 + leshy familiar option. Primal spell list. Druidic Language: add `languages: [druidic]` at char creation. Wild Empathy: `Diplomacy` vs creature DC for animals.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; Wild Shape and order actions require character ownership (`_character_access: TRUE`)
- CSRF expectations: all POST/PATCH class/druid routes require `_csrf_request_header_mode: TRUE`
- Input validation: form id validated against available forms for character level and order; order enum validated server-side
- PII/logging constraints: no PII logged; character id + action type + form id only

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
