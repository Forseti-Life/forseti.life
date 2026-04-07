# Feature Brief: Treasure by Level Table

- Work item id: dc-cr-treasure-by-level
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: in_progress
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch10
- Category: game-mechanic
- Created: 2026-04-07
- DB sections: core/ch10/Treasure
- Depends on: dc-cr-economy, dc-cr-equipment-ch06

## Goal

Implement the treasure-by-party-level table as a GM tool for loot assignment: permanent items and consumables distributed by level, total GP budget per encounter, and integration with the encounter builder's loot recommendations.

## Source reference

> "When you place treasure, use the Treasure by Party Level table to determine the appropriate number and level of items." (Chapter 10: Game Mastering, PF2E Core Rulebook)

## Implementation hint

`TreasureByLevelService::getLootRecommendation(party_level, encounter_difficulty)` returns `{permanent_items[{count, item_level}], consumable_items[{count, item_level}], gp_remainder}`. Data sourced from static server-side table (party levels 1–20 × encounter difficulty levels). `EncounterBuilder::suggestLoot(encounter_id)` calls this service and returns item-level suggestions. GM can assign specific items from the catalog at or below suggested levels. The GP remainder is direct currency reward. Overland hex exploration: weekly loot budget from table for random treasure placement.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: treasure recommendation is GM-only (encounter/session ownership required); player characters cannot query loot budget directly
- CSRF expectations: all POST/PATCH treasure-assignment routes require `_csrf_request_header_mode: TRUE`
- Input validation: party level and encounter difficulty validated as integer within range; item assignment validated against catalog
- PII/logging constraints: no PII logged; encounter id + item ids + gp amount only

## Roadmap section
- Book: core, Chapter: ch10
- REQs: 2340, 2341, 2342, 2343, 2344, 2345
- See `runbooks/roadmap-audit.md` for audit process.
