# Feature Brief: Ranger Class Mechanics

- Work item id: dc-cr-class-ranger
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch03
- Category: class
- Created: 2026-04-07
- DB sections: core/ch03/Ranger
- Depends on: dc-cr-character-class, dc-cr-character-creation

## Goal

Implement the Ranger class with Hunt Prey (target designation with +2 Perception/Tracking bonuses and concealment ignore), Hunter's Edge subclasses (Flurry: −2 MAP, Precision: +1d8 first hit, Outwit: +2 AC), and all 20-level advancement.

## Source reference

> "Rangers are skilled hunters who track their quarry with unparalleled expertise." (Chapter 3: Classes, PF2E Core Rulebook)

## Implementation hint

Hunt Prey: single action, designates one target as `hunted_prey_id` on character entity; bonuses computed dynamically in check resolution when attacker = ranger and target = hunted prey. Hunter's Edge: `hunter_edge` enum field on character. Flurry: if Edge = flurry and attacking hunted prey, MAP = −2/−4. Precision: if Edge = precision, first hit vs hunted prey adds `1d8` (scales to 2d8 at L11, 3d8 at L19). Outwit: +2 circumstance bonus to AC/Reflex vs hunted prey attacks. Trackless Step: leaves no tracks in non-urban environments; server returns `tracking_dc = null`.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; Hunt Prey and all ranger actions require character ownership (`_character_access: TRUE`)
- CSRF expectations: all POST/PATCH class/ranger routes require `_csrf_request_header_mode: TRUE`
- Input validation: hunted prey target id validated as valid encounter participant; Hunter's Edge enum validated server-side
- PII/logging constraints: no PII logged; character id + target id + action type only

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
