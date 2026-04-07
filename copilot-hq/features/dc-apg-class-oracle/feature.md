# Feature Brief: Oracle Class Mechanics (APG)

- Work item id: dc-apg-class-oracle
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: in_progress
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Advanced Player's Guide, Chapter 2 (Oracle)
- Category: game-mechanic
- Created: 2026-04-07
- DB sections: apg/ch02/Oracle
- Depends on: dc-cr-character-class, dc-cr-character-leveling, dc-cr-spellcasting, dc-cr-focus-spells

## Goal

Implement the Oracle class with Mystery subclass (determines spell list and Revelation Spells), the Cursebound escalation mechanic (minor/moderate/major/extreme curse conditions from casting Revelation Spells), and spontaneous divine spellcasting.

## Source reference

> "Oracles are spellcasters who are burdened with divine power, their bodies and minds warped by their chosen mystery." (Advanced Player's Guide, Oracle Class)

## Implementation hint

Character entity gains `oracle_mystery` enum (bones/flames/haunting_hymn/life/lore/stone/tempest/ashes/battle) which determines spell tradition and bonus cantrip/spell access. Cursebound: `curse_level` integer (0–4) on character entity; casting a Revelation Spell increments curse_level; each level applies escalating condition (minor: flavour penalty, moderate: -1 AC+saves, major: frightened 2 per turn, extreme: incapacitated). `OracleCurseManager::curse(character)` increments and applies conditions. Refocus (10-min): decrements curse_level by 1. Revelation Spells are focus spells from the mystery's focus spell list. Spontaneous divine casting otherwise.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; Oracle class actions require character ownership (`_character_access: TRUE`)
- CSRF expectations: all POST/PATCH class/oracle routes require `_csrf_request_header_mode: TRUE`
- Input validation: mystery enum validated server-side; curse level computed server-side as integer (0–4); Revelation Spell ids validated against mystery's allowed list
- PII/logging constraints: no PII logged; character id + spell id + curse level delta + condition applied only

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
