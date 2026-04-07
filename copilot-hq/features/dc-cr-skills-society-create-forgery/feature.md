# Feature Brief: Society — Create Forgery

- Work item id: dc-cr-skills-society-create-forgery
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch04
- Category: skill-action
- Created: 2026-04-07
- DB sections: core/ch04/Society (Int)
- Depends on: dc-cr-skill-system

## Goal

Implement the Society skill's Create a Forgery (Trained), Decipher Writing (Trained, mundane texts), Recall Knowledge (Society, Untrained), and Subsist (Untrained, in settlements), with time-to-create and opposed detection mechanics for forgeries.

## Source reference

> "Society measures your understanding of people, history, and the complicated patterns of civilized life." (Chapter 4: Skills, PF2E Core Rulebook)

## Implementation hint

`SocietyActionResolver`: Create a Forgery: 1-minute activity (or longer for complex documents); produces a forgery entity with `forgery_quality` score = Society check result; when inspected, target makes Perception check vs that DC; success = detects forgery. Decipher Writing (mundane): DC 10 for simple texts, 20 for legal/academic, 30+ for ciphers. Recall Knowledge (Society): DC by cultural/historical topic. Subsist: downtime activity in settlement, DC 15; success = fed for a week at society level. Society also governs knowing NPC contacts and organizational hierarchies.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; Create Forgery and Subsist require character ownership (`_character_access: TRUE`)
- CSRF expectations: all POST/PATCH skill/society routes require `_csrf_request_header_mode: TRUE`
- Input validation: forgery target document type validated; forgery quality score is server-computed roll result, not client-supplied; Subsist settlement id validated
- PII/logging constraints: no PII logged; character id + action id + document id + quality score only

## Roadmap section
- Book: core, Chapter: ch04
- REQs: 1731, 1732, 1733, 1734, 1735, 1736, 1737, 1738
- See `runbooks/roadmap-audit.md` for audit process.
