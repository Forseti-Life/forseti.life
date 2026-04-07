# Feature Brief: APG New Spells

- Work item id: dc-apg-spells
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: in_progress
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Advanced Player's Guide, apg/ch05
- Category: spells
- Created: 2026-04-07
- DB sections: apg/ch05/Spell System (New Spells)
- Depends on: dc-cr-spellcasting

## Goal

Load all APG non-focus spells (~100+ arcane/divine/occult/primal spells) into the spell catalog using the existing spell content type, extending the spell lists available across all spellcasting classes.

## Source reference

> "The Advanced Player's Guide introduces a wide variety of new spells across all four magical traditions." (Advanced Player's Guide, Spells Chapter)

## Implementation hint

APG spells use the same `spell` content type schema as CRB Chapter 7 spells. Load each with `source: apg` and `spell_type: spell`. New spells include: cantrips (Detect Magic improvements), level 1–10 spells across arcane/divine/occult/primal traditions. Some spells are tradition-exclusive. `SpellListService::getAvailableSpells(character, tradition)` already filters by tradition — APG spells appear automatically. Key new spells: Befuddle, Clinging Ice, Daydreamer's Curse, Dragon Breath, Eat Fire, etc. Verify no name conflicts with CRB spell entries.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; spells added to repertoire/spellbook require character ownership (`_character_access: TRUE`)
- CSRF expectations: all POST/PATCH spell-catalog and spell-learn routes require `_csrf_request_header_mode: TRUE`
- Input validation: APG spell ids validated against catalog with `source: apg`; tradition list validated server-side; no duplicate spell id conflicts with CRB
- PII/logging constraints: no PII logged; character id + spell id + tradition + source only

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
