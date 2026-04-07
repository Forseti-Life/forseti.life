# Feature Brief: GMG Chapter 1 — Running the Game

- Work item id: dc-gmg-running-guide
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: in_progress
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Gamemastery Guide, gmg/ch01
- Category: gm-tools
- Created: 2026-04-07
- DB sections: gmg/ch01/Adjudicating Rules, gmg/ch01/Adjudicating Rules (Extended), gmg/ch01/Adventure Design, gmg/ch01/Campaign Structure, gmg/ch01/Chapter Scope and GM Priorities, gmg/ch01/Drawing Maps, gmg/ch01/Encounter Design, gmg/ch01/General Advice, gmg/ch01/Narrative Collaboration, gmg/ch01/Narrative Collaboration (Extended), gmg/ch01/Rarity in Your Game, gmg/ch01/Resolving Problems, gmg/ch01/Resolving Problems (Extended), gmg/ch01/Running Downtime, gmg/ch01/Running Encounters, gmg/ch01/Running Exploration, gmg/ch01/Special Circumstances
- Depends on: dc-cr-gm-tools, dc-cr-encounter-rules, dc-cr-exploration-mode, dc-cr-downtime-mode

## Goal

Implement the GMG's GM reference guide content as searchable in-app GM tools: encounter difficulty budget tables, exploration mode pacing guidance, downtime activity summaries, social encounter DC tables, and XP award guidelines.

## Source reference

> "This chapter provides guidance and tools for running a Pathfinder game." (Gamemastery Guide, Chapter 1)

## Implementation hint

Running Guide content is primarily documentation + quick-reference tables surfaced as searchable Drupal content nodes of type `gm_reference`. Key tables to implement as structured data (not flat text): encounter difficulty budget (party level → XP thresholds), treasure by level, NPC attitude DCs, downtime activity DC table by level. `GMReferenceService::search(query)` returns ranked reference nodes. Tables are rendered as structured `<table>` components, not prose, for quick scanning. Integrates with encounter builder (click-through from budget table to encounter creation).

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: GM reference content is read-only for authenticated GMs; no PII or character data involved
- CSRF expectations: no write routes for reference content; admin update of reference content requires `_admin_access: TRUE` and CSRF token
- Input validation: search query sanitized; reference content is static GM tool data, no user-modifiable fields
- PII/logging constraints: no PII logged; search query + returned node ids only

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
