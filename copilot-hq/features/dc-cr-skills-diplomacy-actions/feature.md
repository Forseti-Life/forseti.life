# Feature Brief: Diplomacy and Intimidation Skill Actions

- Work item id: dc-cr-skills-diplomacy-actions
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch04
- Category: skill-action
- Created: 2026-04-07
- DB sections: core/ch04/Diplomacy (Cha), core/ch04/Intimidation (Cha)
- Depends on: dc-cr-skill-system, dc-cr-gm-narrative-engine

## Goal

Implement all Diplomacy skill actions: Gather Information (Untrained, 2-hour downtime), Make an Impression (Untrained, scene-level social encounter), and Request (Trained), with NPC attitude state machine (Unfriendly/Indifferent/Friendly/Helpful).

## Source reference

> "Diplomacy involves making requests and negotiations using the power of your words." (Chapter 4: Skills, PF2E Core Rulebook)

## Implementation hint

NPC entity gains `attitude` enum (hostile/unfriendly/indifferent/friendly/helpful). Make an Impression: 1-minute activity, Diplomacy vs NPC Will DC (10 + WIS mod + level); success = shift attitude up 1, crit success = up 2. Request: targets Friendly/Helpful NPCs; DC = 15 + NPC level (DC raised for difficult requests); failure may shift attitude down. Gather Information: downtime activity; DC set by rarity of information (10–30); returns rumor/information node IDs. `DiplomacyResolver` stores NPC attitude changes in the scene/encounter context.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; Diplomacy actions against NPCs require session/encounter ownership
- CSRF expectations: all POST/PATCH skill/diplomacy routes require `_csrf_request_header_mode: TRUE`
- Input validation: NPC target id validated as valid scene entity; attitude shifts bounded to valid enum values; information DC sourced server-side
- PII/logging constraints: no PII logged; character id + NPC id + attitude shift + action id only

## Roadmap section
- Book: core, Chapter: ch04, ch10
- REQs: 1669, 1670, 1671, 1672, 1673, 1674, 1675, 1676, 1677,
         1678, 1679, 1680, 1681, 1682, 1683, 2327, 2330
- See `runbooks/roadmap-audit.md` for audit process.
