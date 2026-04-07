# Feature Brief: APG Ancestries and Versatile Heritages

- Work item id: dc-apg-ancestries
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: in_progress
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Advanced Player's Guide, Chapter 1
- Category: game-mechanic
- Created: 2026-04-07
- DB sections: apg/ch01/Additional Ancestry Options (Existing Ancestries), apg/ch01/Ancestries Overview, apg/ch01/Backgrounds, apg/ch01/Catfolk (Amurrun), apg/ch01/Kobold, apg/ch01/Orc, apg/ch01/Ratfolk (Ysoki), apg/ch01/Tengu, apg/ch01/Versatile Heritages
- Depends on: dc-cr-ancestry-system, dc-cr-heritage-system, dc-cr-character-creation

## Goal

Load the 6 APG ancestries (Catfolk, Kobold, Leshy, Orc, Ratfolk, Tengu) into the character creation system, each with HP, Size, Speed, ability boosts/flaws, starting languages, traits, 5+ heritage options, and ancestry feat chains through level 17.

## Source reference

> "The Advanced Player's Guide introduces six new ancestries: catfolk, kobold, leshy, orc, ratfolk, and tengu." (Advanced Player's Guide, Ancestry Chapter)

## Implementation hint

Create one `ancestry` content entity per new ancestry using the same schema as CRB ancestries (dc-cr-dwarf-ancestry). Key values: Catfolk HP 8 DEX+CHA, Kobold HP 6 DEX+CHA flaw STR, Leshy HP 8 CON+WIS, Orc HP 10 STR flaw INT, Ratfolk HP 6 DEX+INT, Tengu HP 6 DEX+WIS. Each has 5+ heritages (`heritage` child entities) and 8+ ancestry feats per level bracket (1/5/9/13/17). `CharacterCreation::getAncestryOptions()` returns combined CRB + APG list. Depends on dc-cr-ancestry-system and dc-cr-heritage-system being shipped.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; ancestry selection requires character ownership (`_character_access: TRUE`)
- CSRF expectations: all POST/PATCH character ancestry routes require `_csrf_request_header_mode: TRUE`
- Input validation: ancestry and heritage ids validated against full catalog (CRB + APG); ability boost/flaw application computed server-side
- PII/logging constraints: no PII logged; character id + ancestry id + heritage id only

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
