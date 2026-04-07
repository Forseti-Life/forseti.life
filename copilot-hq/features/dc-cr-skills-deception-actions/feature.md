# Feature Brief: Deception Skill Actions

- Work item id: dc-cr-skills-deception-actions
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
- DB sections: core/ch04/Deception (Cha)
- Depends on: dc-cr-skill-system, dc-cr-conditions

## Goal

Implement all Deception skill actions: Create a Diversion (Trained), Feint (Trained), Impersonate (Untrained), and Lie (Untrained), with opposed-check resolution against NPC Perception or Sense Motive.

## Source reference

> "Deception allows you to convince others of falsehoods." (Chapter 4: Skills, PF2E Core Rulebook)

## Implementation hint

`DeceptionActionResolver`: Create a Diversion: single action, Deception vs Perception of all observers, success = Hidden until next action; Feint: single action in melee, Deception vs Perception, success = target flat-footed until your next turn; Impersonate: Deception vs Perception (trained NPCs may use Sense Motive) with +4 circumstance bonus if wearing appropriate gear; Lie: Deception vs Sense Motive, context-dependent penalties. All store result in encounter state for downstream condition tracking.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; deception actions require character ownership (`_character_access: TRUE`)
- CSRF expectations: all POST/PATCH skill/deception routes require `_csrf_request_header_mode: TRUE`
- Input validation: target ids validated as encounter participants; impersonation target identity validated as known NPC
- PII/logging constraints: no PII logged; character id + action id + target ids + outcome only

## Roadmap section
- Book: core, Chapter: ch04
- REQs: 1658, 1659, 1660, 1661, 1662, 1663, 1664, 1665, 1666, 1667, 1668
- See `runbooks/roadmap-audit.md` for audit process.
