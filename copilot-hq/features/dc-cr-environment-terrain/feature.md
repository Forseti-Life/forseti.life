# Feature Brief: Environment and Terrain System

- Work item id: dc-cr-environment-terrain
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
- DB sections: core/ch10/Environment
- Depends on: dc-cr-encounter-rules, dc-cr-skill-system

## Goal

Implement terrain and environmental hazard rules: difficult terrain (costs +1 ft/ft movement), greater difficult terrain (+2 ft/ft), hazardous terrain (damage on entry), flanking (opposite-sides = flat-footed), and common terrain types.

## Source reference

> "Difficult terrain is any terrain that impedes movement, requiring you to spend 2 feet of movement for every 1 foot traveled." (Chapter 9: Playing the Game, PF2E Core Rulebook)

## Implementation hint

`TerrainSystem`: Encounter map entity stores `terrain_type` per square (enum: normal/difficult/greater_difficult/hazardous/water/ice/rubble). Movement cost: `MovementCostCalculator::cost(from, to)` returns feet spent. Flanking: `EncounterEngine::isFlanked(attacker, defender, all_participants)` checks if attacker + any ally are on directly opposite sides of defender (diagonals count). Hazardous terrain: triggers `DamageEvent` on entry with damage type/amount from terrain entity. Cover rules: creatures in adjacent squares grant cover (+2 AC) when used as obstacle.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: terrain state is GM-scoped (encounter ownership required to modify); player movement queries are read-only GET
- CSRF expectations: all POST/PATCH terrain update routes require `_csrf_request_header_mode: TRUE`
- Input validation: terrain type validated against allowed enum; flanking computed server-side from position data; hazardous terrain damage type/amount sourced from entity, not client
- PII/logging constraints: no PII logged; encounter id + position + terrain type only

## Roadmap section
- Book: core, Chapter: ch10
- REQs: 2350, 2351, 2352, 2353, 2354, 2355, 2356, 2357, 2358, 2359, 2360, 2361,
         2362, 2363, 2364, 2365, 2366, 2367, 2368, 2369, 2370, 2371, 2372
- See `runbooks/roadmap-audit.md` for audit process.
