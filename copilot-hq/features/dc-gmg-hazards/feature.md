# Feature Brief: GMG Hazards and Traps

- Work item id: dc-gmg-hazards
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: in_progress
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Gamemastery Guide, gmg/ch02
- Category: gm-tools
- Created: 2026-04-07
- DB sections: gmg/ch02/Baseline Requirements, gmg/ch02/Integration Notes
- Depends on: dc-cr-encounter-rules

## Goal

Implement the expanded hazards system from the GMG: complex hazards with initiative and per-round routines, environmental vs trap hazard categories, stealth/disable DCs, hardness/HP/BT for destroyable hazards, and encounter builder integration.

## Source reference

> "Hazards are traps, natural phenomena, and similar dangers that can be encountered in adventures." (Gamemastery Guide, Chapter 2)

## Implementation hint

Hazard entity: `hazard_type` (environmental/trap), `complexity` (simple/complex), `stealth_dc`, `disable_skill`, `disable_dc`, `ac`, `saves{fort,ref,will}`, `hardness`, `hp`, `break_threshold`, `reset_type`, `trigger`, `routine[]` (for complex hazards: list of per-round actions). Complex hazard: joins encounter initiative order; `HazardInitiativeService::addToEncounter(hazard_id, encounter_id)`. `HazardDisableService::attempt(character, hazard_id)` resolves skill check, tracks disable progress (some hazards require multiple successes). Environmental hazard: triggered automatically; no initiative. Encounter builder: adds hazards as encounter participants with XP budget contribution.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: hazard creation/modification is GM-scoped (session ownership required); player characters interact via skill checks only
- CSRF expectations: all POST/PATCH hazard routes require `_csrf_request_header_mode: TRUE`
- Input validation: hazard entity ids validated from catalog; disable skill validated against allowed skill enum; no client-supplied DC overrides
- PII/logging constraints: no PII logged; encounter id + hazard id + character id + disable result only

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
