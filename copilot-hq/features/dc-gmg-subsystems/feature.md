# Feature Brief: GMG Chapter 4 — Subsystems

- Work item id: dc-gmg-subsystems
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: in_progress
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Gamemastery Guide, gmg/ch03-ch04
- Category: gm-tools
- Created: 2026-04-07
- DB sections: gmg/ch03/Baseline Requirements, gmg/ch03/Integration Notes, gmg/ch04/Baseline Requirements, gmg/ch04/Integration Notes
- Depends on: dc-cr-encounter-rules, dc-cr-exploration-mode, dc-cr-downtime-mode

## Goal

Implement the GMG structured subsystems as playable mini-games: Chases (obstacle track), Influence (social HP pool), Research (knowledge accumulation), Infiltration (security threshold), and Reputation (faction standing).

## Source reference

> "Subsystems are specialized frameworks for running specific types of dramatic scenes." (Gamemastery Guide, Chapter 2)

## Implementation hint

Each subsystem is a Drupal content type: `subsystem` with `subsystem_type` enum. Chase: `obstacles[]` each with `skill_options[]` and `DC`; participants take turns rolling vs obstacle; failure = chase points lost. Influence: `npc_influence_pool` + `influence_thresholds`; party talks to NPCs, rolls skills to chip away at pool. Research: `research_topic` + `knowledge_segments[]`; accumulate research points to unlock clues. Infiltration: `security_level` (total points to breach); party distributes infiltration actions. Reputation: faction entity with `standing` integer (−5 to +5); actions shift standing. `SubsystemEngine::processTurn(subsystem_id, participant_id, action)` is the shared entry point.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: subsystem GM setup requires session ownership; participant actions require character ownership
- CSRF expectations: all POST/PATCH subsystem routes require `_csrf_request_header_mode: TRUE`
- Input validation: subsystem entity ids validated; skill rolls and DC outcomes computed server-side; standing adjustments bounded to valid range (−5 to +5)
- PII/logging constraints: no PII logged; session id + subsystem id + participant id + action type + outcome only

## Roadmap section
- Book: gmg, Chapter: ch04
- REQs: 2732, 2733, 2734, 2735, 2736, 2737
- See `runbooks/roadmap-audit.md` for audit process.
