# Feature Brief: APG New Rituals

- Work item id: dc-apg-rituals
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: in_progress
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: apg/ch05
- Category: spells
- Created: 2026-04-06
- DB sections: apg/ch05/Rituals (APG New Rituals)
- Depends on: dc-cr-rituals

## Goal

Load all APG rituals into the ritual catalog using the existing ritual content type, extending the ritual pool available for GMs and high-level characters to cast multi-caster, long-duration spells.

## Source reference

> "Rituals are special spells that take much longer to cast and often require multiple participants." (Advanced Player's Guide, Spells Chapter)

## Implementation hint

APG rituals use the same ritual schema as CRB: `spell_type: ritual`, `cast_time` (hours/days), `primary_check`, `secondary_checks[]`, `secondary_casters_min`, `cost`, `effect` by degree of success. Load each as a Drupal spell entity with `source: apg`. `RitualService::castRitual(primary_caster, secondary_casters[], ritual_id)` already handles the check resolution and outcome — APG rituals slot in without new logic. Examples: Animate Object, Commune, Field of Razors, Freedom, Planar Ally. No schema changes needed.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; ritual initiation requires session ownership and primary caster character ownership
- CSRF expectations: all POST/PATCH ritual-casting routes require `_csrf_request_header_mode: TRUE`
- Input validation: ritual id validated against catalog; secondary caster ids validated as session participants; material cost deducted server-side atomically
- PII/logging constraints: no PII logged; session id + ritual id + caster ids + outcome only

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
