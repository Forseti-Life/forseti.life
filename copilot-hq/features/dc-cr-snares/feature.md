# Feature Brief: Snares (Core + APG)

- Website: dungeoncrawler
- Type: new
- Module: dungeoncrawler_content
- Priority: P2
- Status: in_progress
- Release: none
- Dependencies: dc-cr-equipment-system, dc-cr-skill-system, dc-cr-class-ranger

## Goal

Implement the Snares system for rangers and Snare Crafting feat users: snare entities (Alarm/Hampering/Marking/Wounding), placement in exploration mode, trigger mechanics when enemy enters square, and Crafting skill resolution.

## Source reference

> "Snares are one-use traps that you can quickly set in the field." (Chapter 6: Equipment, PF2E Core Rulebook)

## Implementation hint

Snare entity: `snare_type` enum (alarm/hampering/marking/wounding), `level`, `craft_dc`, `trigger_type` (creature enters square), `effect`. `SnareManager::placeSnare(character, snare_id, map_position)` validates Crafting check vs craft_dc and materials, then adds snare entity to map square. Encounter engine: `onCreatureEnterSquare(creature, position)` checks `map.snares_at(position)`, triggers snare effect, removes snare. Ranger class feature (Snare Specialist) allows placing snares faster and reduces material costs. Snares require character to have `snare_crafting_feat = true` unless Ranger.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; snare placement requires character ownership and encounter ownership for map write
- CSRF expectations: all POST/PATCH snare routes require `_csrf_request_header_mode: TRUE`
- Input validation: snare type validated against snare catalog; map position validated within encounter bounds; material cost enforced server-side
- PII/logging constraints: no PII logged; character id + snare id + position + trigger result only

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
