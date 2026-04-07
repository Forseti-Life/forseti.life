# Feature Brief: APG Focus Spells

- Work item id: dc-apg-focus-spells
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: in_progress
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: apg/ch05
- Category: spells
- Created: 2026-04-06
- DB sections: apg/ch05/Focus Spells (APG)
- Depends on: dc-cr-focus-spells, dc-apg-class-oracle, dc-apg-class-witch

## Goal

Load all APG focus spells (Oracle Revelation Spells, Witch Hexes, and new class focus spells for Champion, Cleric, Druid, etc.) into the spell catalog, tagged as focus spells with their owning class or archetype.

## Source reference

> "Focus spells are special spells tied to a specific class or archetype, powered by focus points." (Advanced Player's Guide, Spells Chapter)

## Implementation hint

APG focus spells use the same `spell` content type as CRB spells with `is_focus_spell: true` and `focus_class_source` FK (links to class entity). Oracle Revelation Spells: 9 mysteries × 3–4 spells each, tagged `oracle_mystery_id`. Witch Hexes: patron-dependent, tagged `witch_patron_id`. Champion focus spells: by cause (paladin/liberator/redeemer). Load all as Drupal spell entities with `source: apg` and `spell_type: focus`. `FocusSpellManager::getAvailableSpells(character)` filters by class and source — APG spells appear automatically once character has the granting class feature.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; focus spell casting requires character ownership and valid focus point pool
- CSRF expectations: all POST/PATCH spell-casting routes require `_csrf_request_header_mode: TRUE`
- Input validation: focus spell id validated against class source; focus point deduction validated against pool; mystery/patron id validated for oracle/witch routing
- PII/logging constraints: no PII logged; character id + spell id + focus point delta + class source only

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
