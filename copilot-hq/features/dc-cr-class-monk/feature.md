# Feature Brief: Monk Class Mechanics

- Work item id: dc-cr-class-monk
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
- DB sections: core/ch03/Monk
- Depends on: dc-cr-character-class, dc-cr-focus-spells

## Goal

Implement the Monk class with Flurry of Blows (reduced MAP), Powerful Fist, ki tradition (no-spell vs ki-spell variant), stance system (Tiger/Wolf/Dragon/Mountain/Ironblood), Stunning Fist, and all 20 levels of advancement.

## Source reference

> "Monks are warriors who use both their physical and spiritual power in their pursuit of personal perfection." (Chapter 3: Classes, PF2E Core Rulebook)

## Implementation hint

Flurry of Blows: 2 strikes action with MAP only −4/−8 instead of −5/−10; both must be unarmed or monk weapons. Powerful Fist: unarmed strike deals 1d6 B, not 1d4; agile, finesse traits. Stances are conditions applied to character; each grants modified strike or special action. Ki tradition: if character has ki feats, add `ki_point_pool` field. `StanceManager::enterStance(character, stance_id)` deactivates current stance and applies new one. Stunning Fist: if Strike hits, target must save vs Fortitude (DC = class DC) or be stunned 1.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; stance changes and ki actions require character ownership (`_character_access: TRUE`)
- CSRF expectations: all POST/PATCH class/monk routes require `_csrf_request_header_mode: TRUE`
- Input validation: stance id validated against monk stance enum; ki point expenditure validated against pool; Flurry of Blows validates both strike targets are unarmed/monk weapons
- PII/logging constraints: no PII logged; character id + action type + stance id only

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
