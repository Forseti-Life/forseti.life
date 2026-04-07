# Feature Brief: Arcana — Borrow an Arcane Spell

- Work item id: dc-cr-skills-arcana-borrow-spell
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch04
- Category: skill-action
- Created: 2026-04-07
- DB sections: core/ch04/Arcana (Int)
- Depends on: dc-cr-skill-system, dc-cr-spellcasting

## Goal

Implement the Arcana skill's Borrow an Arcane Spell action (Trained, for wizards borrowing from another's spellbook), Decipher Writing (Trained), Identify Magic (Trained, arcane), and Learn a Spell (Trained, add to spellbook).

## Source reference

> "Arcana measures how much you know about arcane magic and creatures." (Chapter 4: Skills, PF2E Core Rulebook)

## Implementation hint

`ArcanaActionResolver`: Borrow an Arcane Spell: requires another character's spellbook in possession; Arcana DC = 20 + spell level; success = can prepare borrowed spell today only (not added to own spellbook). Learn a Spell: 10-minute activity, Arcana DC = 20 + spell level, costs `level × 10gp` materials; success = spell added to spellbook permanently. Identify Magic (Arcane): DC = 20 + item/spell level, 10-minute activity. Decipher Writing: DC by text complexity (10 simple, 20 scholarly, 30+ magical script). All results stored in encounter/session context.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; Borrow and Learn Spell require character ownership; borrowed spell access validated against source character's spellbook
- CSRF expectations: all POST/PATCH skill/arcana routes require `_csrf_request_header_mode: TRUE`
- Input validation: spell id validated as arcane spell; gold cost validated and deducted server-side; borrowed spell grant is session-scoped only (not permanent)
- PII/logging constraints: no PII logged; character id + spell id + source character id (if borrowing) + outcome only

## Roadmap section
- Book: core, Chapter: ch04
- REQs: 1616, 1617, 1618
- See `runbooks/roadmap-audit.md` for audit process.
