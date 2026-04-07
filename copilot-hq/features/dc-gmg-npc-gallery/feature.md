# Feature Brief: GMG NPC Gallery System

- Work item id: dc-gmg-npc-gallery
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: in_progress
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Gamemastery Guide, gmg/ch02
- Category: gm-tools
- Created: 2026-04-07
- DB sections: (sub-feature of gmg/ch02 Tools chapter — umbrella covered by dc-gmg-hazards)
- Depends on: dc-cr-npc-system

## Goal

Implement the GMG NPC Gallery as a pre-built NPC stat block library (Bandit, Guard, Mage, Noble, Merchant, etc.) that GMs can import directly into encounters without manual stat block entry.

## Source reference

> "The NPC Gallery provides ready-made NPCs with complete stat blocks for common roles." (Gamemastery Guide, Chapter 5)

## Implementation hint

NPC Gallery entity shares the same `creature` content type as monsters but is tagged with `source: gmg_npc_gallery`. `NPCGalleryService::listByRole(role_enum)` returns pre-built stat blocks. `EncounterBuilder::importNPC(npc_template_id, encounter_id)` clones the stat block into an encounter-scoped creature instance (separate from template). GM can customize after import (name, HP, equipment). Gallery covers roles: bandit, guard, merchant, noble, mage, spy, soldier, scholar, acolyte, thug. Stat blocks include level, HP, AC, attacks, skills, saves.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: NPC gallery browsing is read-only for authenticated GMs; import into encounter requires session ownership
- CSRF expectations: all POST/PATCH NPC import routes require `_csrf_request_header_mode: TRUE`
- Input validation: NPC template id validated against gallery catalog; cloned instance fields validated on import; custom modifications validated against stat block schema
- PII/logging constraints: no PII logged; encounter id + npc template id + customized fields only

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
