# Feature Brief: Thievery Skill Actions

- Work item id: dc-cr-skills-thievery-disable-pick-lock
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P1
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch04
- Category: skill-action
- Created: 2026-04-07
- DB sections: core/ch04/Thievery (Dex)
- Depends on: dc-cr-skill-system, dc-cr-hazards

## Goal

Implement all Thievery skill actions: Disable Device (Trained), Pick a Lock (Trained), Palm an Object (Trained), and Steal (Trained), with DC tables by complexity/lock grade and integration with the trap/hazard system.

## Source reference

> "Thievery measures your ability to perform tasks requiring quick fingers and sleight of hand." (Chapter 4: Skills, PF2E Core Rulebook)

## Implementation hint

`ThieveryActionResolver`: Disable Device: DC set by hazard complexity (Simple 15, Average 20, Good 25, Superior 30); requires thieves' tools; each success reduces hazard's remaining disable counts. Pick a Lock: DC by lock grade (Poor 15, Average 20, Good 25, Superior 30, Masterwork 40); 2 actions per attempt; requires thieves' tools; crit failure = jam (DC +5). Palm an Object: Thievery vs Perception; success = pocketed without notice. Steal: Thievery vs Perception; more difficult if target is in combat or guarded. Lock and hazard DCs loaded from server-side entity fields.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; Thievery actions require character ownership (`_character_access: TRUE`)
- CSRF expectations: all POST/PATCH skill/thievery routes require `_csrf_request_header_mode: TRUE`
- Input validation: target lock/hazard id validated as valid scene entity; tool requirement enforced server-side; DC sourced from entity complexity field
- PII/logging constraints: no PII logged; character id + target id + action id + outcome only

## Roadmap section
- Book: core, Chapter: ch04
- REQs: 1747, 1748, 1749, 1750, 1751, 1752, 1753, 1754, 1755, 1756
- See `runbooks/roadmap-audit.md` for audit process.
