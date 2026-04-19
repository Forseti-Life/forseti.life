# Feature Brief: Bestiary 3 (deferred)

- Work item id: dc-b3-bestiary3
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: deferred
- Priority: P3
- Deferred until: dc-b2-bestiary2 ships
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Bestiary 3
- Category: creature-library
- Created: 2026-04-07
- DB sections: b3/s01/Baseline Requirements, b3/s01/Integration Notes, b3/s02/Baseline Requirements, b3/s02/Integration Notes, b3/s03/Baseline Requirements, b3/s03/Integration Notes
- Depends on: dc-cr-encounter-rules, dc-cr-npc-system, dc-b1-bestiary1

## Goal

Implement the Bestiary 3 creature library expansion: additional creature stat blocks including extraplanar entities, mythic creatures, and rare/unique monster types. Extends the `creature` content type with ~300+ additional entries. Covers 18 requirements across `b3/s01–s03`.

## Source reference

> "Bestiary 3 introduces hundreds of new monsters, including powerful extraplanar beings, unusual creature variants, and expanded creature families." (Bestiary 3 — Introduction)

## Implementation hint

Extension of `dc-b1-bestiary1` schema — no new content type fields needed; this feature is a data load. Requires both Bestiary 1 (schema) and Bestiary 2 (data pipeline pattern) to be complete. Bestiary 3 introduces some extraplanar mechanics (planar traits, dimensional traits) that may require minor schema extensions — identify during import scaffolding. Import pipeline: drush batch import matching B1/B2 pattern. DB sections contain baseline data-model and integration-note placeholders.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: creature library is read-only for players; GM-only mutation routes require `_campaign_gm_access: TRUE`
- CSRF expectations: any POST/PATCH creature import or override routes require `_csrf_request_header_mode: TRUE`
- Input validation: stat block fields validated against defined types and ranges; extraplanar trait fields validated against enum; import pipeline sanitizes all text fields
- PII/logging constraints: no PII logged; creature id + encounter id + action type only
