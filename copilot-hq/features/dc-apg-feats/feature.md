# Feature Brief: APG General and Skill Feats

- Work item id: dc-apg-feats
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: in_progress
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: apg/ch04
- Category: feats
- Created: 2026-04-06
- DB sections: apg/ch04/General Feats Overview, apg/ch04/Non-Skill General Feats, apg/ch04/Skill Feats — Acrobatics, apg/ch04/Skill Feats — Athletics, apg/ch04/Skill Feats — Crafting, apg/ch04/Skill Feats — Deception, apg/ch04/Skill Feats — Diplomacy, apg/ch04/Skill Feats — Intimidation, apg/ch04/Skill Feats — Lore (Warfare), apg/ch04/Skill Feats — Medicine, apg/ch04/Skill Feats — Multi-Skill (Varying), apg/ch04/Skill Feats — Nature, apg/ch04/Skill Feats — Occultism, apg/ch04/Skill Feats — Performance, apg/ch04/Skill Feats — Religion, apg/ch04/Skill Feats — Society, apg/ch04/Skill Feats — Stealth, apg/ch04/Skill Feats — Survival, apg/ch04/Skill Feats — Thievery
- Depends on: dc-cr-skill-system, dc-cr-general-feats, dc-cr-skill-feats

## Goal

Load all APG feats (~200+ ancestry, general, and skill feats) into the feat catalog using the existing feat content type, extending the pools available to all characters during feat selection.

## Source reference

> "The Advanced Player's Guide includes hundreds of new feats for characters of all types." (Advanced Player's Guide, Feats Chapter)

## Implementation hint

APG feats use the same `feat` content type schema as Chapter 5 CRB feats. Load each as a Drupal feat entity with `source: apg`. New feats include: APG ancestry feats for both CRB and APG ancestries, new general feats, new skill feats, and new archetype feats. `FeatManager::getAvailableFeats(character, slot_type)` already filters by character ancestry/level/prerequisites — APG feats will automatically appear when their prerequisites are met. No new services needed; purely a data load. Verify no duplicate feat names with CRB entries before import.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; feat selection requires character ownership (`_character_access: TRUE`)
- CSRF expectations: all POST/PATCH feat-selection routes require `_csrf_request_header_mode: TRUE`
- Input validation: APG feat ids validated against catalog; prerequisite validation enforced server-side; no duplicate feat id conflicts with CRB
- PII/logging constraints: no PII logged; character id + feat id + slot type only

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
