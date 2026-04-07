# Feature Brief: Resting, Watch Schedule, and Starvation/Thirst

- Work item id: dc-cr-rest-watch-starvation
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
- DB sections: core/ch10/Resting and Daily Preparations
- Depends on: dc-cr-encounter-rules, dc-cr-conditions

## Goal

Implement rest mechanics (8-hour recovery of HP and spell slots), long rest (24-hour full recovery), watch rotation tracking, and starvation/thirst condition escalation for characters without food or water.

## Source reference

> "When you take a full night's rest of at least 8 hours, you regain your spell slots and a certain number of Hit Points." (Chapter 9: Playing the Game, PF2E Core Rulebook)

## Implementation hint

`RestService::rest(session_id, watch_assignments[])` resolves 8-hour rest: each character recovers `character.level × (CON_modifier + 1)` HP (min 1/level); all spell slots reset; Focus Points reset to 1; daily prep triggered (formula items, invested items). Watch assignments: array of character ids for each watch period; watcher makes Perception check for random encounters. `StarvationTracker`: tracks `days_without_food` and `days_without_water` per character; applies stacking `Enfeebled` condition (Enfeebled 1 per missed day; remove 1 per day fed). Long rest (24hrs): recovers `doubled` HP per level.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: rest is session-scoped (session ownership required); character HP recovery is server-computed, not client-supplied
- CSRF expectations: all POST rest routes require `_csrf_request_header_mode: TRUE`
- Input validation: watch assignment character ids validated as session participants; HP recovery computed server-side from character stats; starvation counters validated as non-negative integers
- PII/logging constraints: no PII logged; session id + character ids + HP delta + starvation counter only

## Roadmap section
- Book: core, Chapter: ch10
- REQs: 2346, 2347, 2348, 2349
- See `runbooks/roadmap-audit.md` for audit process.
