# Feature Brief: Encounter Creature XP Table

- Work item id: dc-cr-encounter-creature-xp-table
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: in_progress
- Priority: P1
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch10
- Category: game-mechanic
- Created: 2026-04-07
- DB sections: core/ch10/Encounter Building
- Depends on: dc-cr-encounter-rules

## Goal

Implement the XP-per-creature table (indexed by creature level minus party level) and encounter difficulty budgeter, with automatic XP award to all party members at encounter end.

## Source reference

> "After the encounter ends, you get XP based on the level of the creatures you defeated." (Chapter 10: Game Mastering, PF2E Core Rulebook)

## Implementation hint

Static XP delta table: party−4=10, −3=15, −2=20, −1=30, 0=40, +1=60, +2=80, +3=120, +4=160. `EncounterXPService::calculateCreatureXP(creature_level, party_level)` returns XP per creature. Encounter total XP = sum of all creature XPs. Encounter difficulty thresholds (budget): Trivial ≤40, Low ≤60, Moderate ≤80, Severe ≤120, Extreme ≤160. `EncounterResolve::awardXP(encounter_id)` distributes encounter total evenly to all participating party members. Level up triggers at `character.xp >= 1000` (resets to 0).

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: XP award is GM-triggered (encounter ownership required); level-up confirmation requires character ownership
- CSRF expectations: all POST/PATCH xp-award routes require `_csrf_request_header_mode: TRUE`
- Input validation: creature level and party level validated as integers within PF2E range (−1 to 25); XP delta sourced from server-side table only; no client-supplied XP values
- PII/logging constraints: no PII logged; encounter id + character id + xp delta + level-up flag only

## Roadmap section
- Book: core, Chapter: ch10
- REQs: 2312, 2314, 2315, 2316, 2317
- See `runbooks/roadmap-audit.md` for audit process.
