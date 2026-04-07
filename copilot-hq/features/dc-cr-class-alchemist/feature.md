# Feature Brief: Alchemist Class Mechanics

- Work item id: dc-cr-class-alchemist
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch03
- Category: class
- Created: 2026-04-07
- DB sections: core/ch03/Alchemist
- Depends on: dc-cr-character-class, dc-cr-character-creation, dc-cr-equipment-system

## Goal

Implement the Alchemist class with all 20 levels of class features: infused reagents pool, advanced alchemy (daily prep crafting), quick alchemy (1-action in-combat crafting), formula book, and research fields (Bomber, Chirurgeon, Mutagenist).

## Source reference

> "The alchemist uses their knowledge of the alchemical sciences to alter the world around them." (Chapter 3: Classes, PF2E Core Rulebook)

## Implementation hint

Character entity gains `infused_reagents` field (value = level + INT modifier). Daily prep phase: call `AlchemistService::advancedAlchemy(character, formulas, reagents_spent)` → produces infused items list. In-combat: `quickAlchemy(character, formula_id)` spends 1 reagent, returns item instantly usable until end of turn. Research field is an enum stored on character; it determines bonus formula options and a free daily advanced alchemy item. Formula book is a character asset list; `learnFormula(character, item_id)` adds to it after Crafting check.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; alchemist actions require character ownership (`_character_access: TRUE`)
- CSRF expectations: all POST/PATCH class/alchemist routes require `_csrf_request_header_mode: TRUE`
- Input validation: research field enum validated server-side; infused reagent counts validated against pool; formula id validated against character's formula book
- PII/logging constraints: no PII logged; character id + action type + item id + reagent delta only

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
