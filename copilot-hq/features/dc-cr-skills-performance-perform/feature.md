# Feature Brief: Performance Skill Actions

- Work item id: dc-cr-skills-performance-perform
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
- DB sections: core/ch04/Performance (Cha)
- Depends on: dc-cr-skill-system

## Goal

Implement the Performance skill's Perform action (Untrained, Earn Income for street performers) and its integration with Bard Composition Spell DCs where Performance modifier is used.

## Source reference

> "Performance measures how skilled you are at captivating an audience with various forms of entertainment." (Chapter 4: Skills, PF2E Core Rulebook)

## Implementation hint

`PerformanceActionResolver`: Perform: standard action or downtime activity; DC set by audience size/sophistication or GM-assigned; success = entertain audience; used primarily for Earn Income downtime (same table as Lore). `EarnIncomeService` accepts `skill: 'performance'` for entertainers. Bard integration: some Composition Spell save DCs use `10 + Performance modifier` instead of spell DC; `BardSpellResolver` checks `spell.uses_performance_dc` flag and substitutes modifier. Perform for different audiences: busking (DC 15), tavern (DC 20), noble court (DC 30+).

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; Performance actions require character ownership (`_character_access: TRUE`)
- CSRF expectations: all POST/PATCH skill/performance routes require `_csrf_request_header_mode: TRUE`
- Input validation: audience DC sourced from server-side tables or GM-authored value with range validation; income GP delta computed server-side
- PII/logging constraints: no PII logged; character id + performance type + audience id + gp delta only

## Roadmap section
- Book: core, Chapter: ch04
- REQs: 1716, 1717, 1718, 1719, 1720
- See `runbooks/roadmap-audit.md` for audit process.
