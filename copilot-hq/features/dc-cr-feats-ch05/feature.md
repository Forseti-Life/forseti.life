# Feature Brief: Core Book Chapter 5 — Feats Overview and Key Mechanics

- Work item id: dc-cr-feats-ch05
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch05
- Category: feats
- Created: 2026-04-07
- DB sections: core/ch05/Chapter Overview, core/ch05/Key Feat Mechanic Notes, core/ch05/Non-Skill General Feats Table
- Depends on: dc-cr-general-feats, dc-cr-skill-feats, dc-cr-character-leveling

## Goal

Implement the feat system with content type (name, type, level, prerequisites, traits, effect), character feat slot allocation by type (Ancestry/Class/General/Skill feats at appropriate levels), and prerequisite validation on selection.

## Source reference

> "You gain feats at 1st level and beyond. Feats represent abilities outside the normal scope of your training." (Chapter 5: Feats, PF2E Core Rulebook)

## Implementation hint

Feat content entity: `name`, `feat_type` enum (ancestry/class/general/skill/archetype), `level`, `prerequisites[]` (feat ids + level/ability requirements), `traits[]`, `effect_text`. `FeatManager::selectFeat(character, feat_id, slot_type)` validates: slot type matches feat type, character level ≥ feat level, all prerequisites met. Character entity gains `feats[slot_type][slot_index]` structure. Ancestry feats: slots at L1/5/9/13/17. General feats: L3/7/11/15/19. Skill feats: every even level. `PrerequisiteValidator` checks chained feat requirements recursively.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; feat selection requires character ownership (`_character_access: TRUE`)
- CSRF expectations: all POST/PATCH feat-selection routes require `_csrf_request_header_mode: TRUE`
- Input validation: feat id validated against feat catalog; prerequisite validation server-side only (no client-bypass); slot type and level gating enforced server-side
- PII/logging constraints: no PII logged; character id + feat id + slot type + slot index only

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
