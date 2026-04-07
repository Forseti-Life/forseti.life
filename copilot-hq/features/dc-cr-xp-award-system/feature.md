# Feature Brief: XP Award System

- Work item id: dc-cr-xp-award-system
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: in_progress
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch10
- Category: game-mechanic
- Created: 2026-04-07
- DB sections: core/ch10/Experience Points and Advancement
- Depends on: dc-cr-encounter-creature-xp-table, dc-cr-character-leveling

## Goal

Implement the full XP award system: per-creature XP (indexed by level delta), objective/milestone XP bonuses, automatic distribution to party, and level-up trigger at 1000 XP (plus milestone leveling as an alternative mode).

## Source reference

> "The GM assigns XP at the end of an encounter or session to all participants." (Chapter 10: Game Mastering, PF2E Core Rulebook)

## Implementation hint

`XPAwardService::awardCreatureXP(encounter_id)` → sums XP per creature using delta table; `awardObjectiveXP(session_id, objective_id)` → fixed values (major=80, minor=40, trivial=10) from content entity. Distribution: divide total evenly among all participating characters. Level trigger: `CharacterProgressionService::checkLevelUp(character)` fires when `xp >= 1000`; awards level, resets XP to 0. Milestone mode: `session.xp_mode = 'milestone'`; GM manually triggers `LevelUpService::grantLevel(character)` without XP tracking. `XPLog` entity stores each award event for audit trail (session id + character id + source + amount).

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: XP awards are GM-triggered (session/encounter ownership required); direct level-up route requires `_gm_access: TRUE`
- CSRF expectations: all POST/PATCH xp-award routes require `_csrf_request_header_mode: TRUE`
- Input validation: XP award amounts sourced from server-side tables or content entities; no client-supplied raw XP values; milestone level grant requires GM auth
- PII/logging constraints: no PII logged; session id + character id + xp source + amount only

## Roadmap section
- Book: core, Chapter: ch10
- REQs: 2332–2335, 2337–2339
- See `runbooks/roadmap-audit.md` for audit process.
