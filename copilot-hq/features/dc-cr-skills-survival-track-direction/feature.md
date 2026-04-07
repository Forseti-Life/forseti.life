# Feature Brief: Survival — Sense Direction, Track, Cover Tracks

- Work item id: dc-cr-skills-survival-track-direction
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
- DB sections: core/ch04/Survival (Wis)
- Depends on: dc-cr-skill-system, dc-cr-exploration-mode

## Goal

Implement all Survival skill actions: Sense Direction (Untrained), Subsist (Untrained, downtime), Track (Trained), and Cover Tracks (Trained), with exploration-mode integration for overland travel.

## Source reference

> "Survival measures your ability to live in the wild and find your way in the natural world." (Chapter 4: Skills, PF2E Core Rulebook)

## Implementation hint

`SurvivalActionResolver`: Sense Direction: DC 15 in familiar terrain, 20 in wilderness, +5 without sky visibility; returns compass direction result. Subsist: downtime activity; DC 15 for wilderness, 20 for most environments; success = fed for 1 week; crit success = others too; failure = fatigued next day. Track: DC set by creature's Survival/nature of terrain; success while moving at half-speed; requires 1 hour per hex. Cover Tracks: imposes −4 to Track DCs following you; requires Survival ≥ trained. All exploration-mode paced activities tracked in session state.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; Survival actions require character ownership (`_character_access: TRUE`)
- CSRF expectations: all POST/PATCH skill/survival routes require `_csrf_request_header_mode: TRUE`
- Input validation: terrain type validated against allowed enum; Track target id validated as valid footprint trail entity
- PII/logging constraints: no PII logged; character id + action id + terrain + outcome only

## Roadmap section
- Book: core, Chapter: ch04
- REQs: 1572, 1573, 1595, 1596, 1597, 1598, 1739, 1740, 1741, 1742, 1743, 1744, 1745, 1746
- See `runbooks/roadmap-audit.md` for audit process.
