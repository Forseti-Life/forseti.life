# Feature Brief: APG Equipment, Magic Items, and Alchemical Items

- Work item id: dc-apg-equipment
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: in_progress
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: apg/ch05, apg/ch06
- Category: equipment
- Created: 2026-04-06
- DB sections: apg/ch05/Alchemical Items, apg/ch05/Consumable Magic Items, apg/ch05/New Weapons and Adventuring Gear, apg/ch05/Permanent Magic Items, apg/ch05/Snares, apg/ch06/Alchemical Items, apg/ch06/Consumable Magic Items, apg/ch06/New Weapons and Adventuring Gear, apg/ch06/Permanent Magic Items, apg/ch06/Snares
- Depends on: dc-cr-equipment-system, dc-cr-magic-items, dc-cr-alchemical-items

## Goal

Load all APG equipment entries (new weapons, armor, alchemical items, magic items, and siege weapons) into the equipment catalog using the existing Item content type schema from dc-cr-equipment-ch06.

## Source reference

> "The Advanced Player's Guide introduces new weapons, alchemical items, and magical equipment." (Advanced Player's Guide, Equipment Chapter)

## Implementation hint

APG equipment uses the same `item` content type schema as Chapter 6 CRB equipment. Load each APG item as a Drupal item entity with `source: apg`. New item categories include: new martial/exotic weapons (gnome flickmace, tengu gale blade, etc.), new alchemical bombs and elixirs, new magical weapons/armor with property runes, and advanced siege weapons. `EquipmentService::getAvailableItems(source_filter)` already supports source filtering for character creation. No schema changes needed; purely a data load extending the existing catalog.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; equip/purchase requires character ownership (`_character_access: TRUE`)
- CSRF expectations: all POST/PATCH equipment routes require `_csrf_request_header_mode: TRUE`
- Input validation: APG item ids validated against catalog with `source: apg` tag; equip slot compatibility enforced server-side
- PII/logging constraints: no PII logged; character id + item id + action type only

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
