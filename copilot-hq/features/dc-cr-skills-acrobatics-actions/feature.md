# Feature Brief: Acrobatics Skill Actions

- Work item id: dc-cr-skills-acrobatics-actions
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
- DB sections: core/ch04/Acrobatics (Dex)
- Depends on: dc-cr-skill-system, dc-cr-skills-calculator-hardening

## Goal

Implement all Acrobatics skill actions: Balance (Trained), Tumble Through (Trained), Maneuver in Flight (Trained), and Squeeze (Untrained), with full degree-of-success outcomes for each.

## Source reference

> "Acrobatics measures your ability to perform tasks requiring coordination and grace." (Chapter 4: Skills, PF2E Core Rulebook)

## Implementation hint

`AcrobaticsActionResolver` handles 4 actions. Balance: DC = terrain hazard DC (10 for normal uneven, 20+ for narrow/unstable); crit success = move normally, success = move at half, failure = flat-footed/hampered, crit fail = fall prone. Tumble Through: DC = target Reflex DC; success = move through enemy space; failure = movement ends. Maneuver in Flight: DC by maneuver type, requires flight speed. Squeeze: DC 20 for tight spaces (smaller than Small); slower movement, flat-footed. Each action calls `SkillActionResolver::resolve(action, character, context)` → `DegreeOfSuccess` enum.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; acrobatics actions require character ownership (`_character_access: TRUE`)
- CSRF expectations: all POST/PATCH skill/acrobatics routes require `_csrf_request_header_mode: TRUE`
- Input validation: terrain DC values sourced from server-side tables; target id for Tumble Through validated as encounter participant
- PII/logging constraints: no PII logged; character id + action id + roll result + DC only

## Roadmap section
- Book: core, Chapter: ch04
- REQs: 1603, 1604, 1605, 1606, 1607, 1608, 1609, 1610, 1611, 1612, 1613, 1614
- See `runbooks/roadmap-audit.md` for audit process.
