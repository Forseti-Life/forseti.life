# Feature Brief: APG Core Class Expansions

- Work item id: dc-apg-class-expansions
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: in_progress
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Advanced Player's Guide, Chapter 2 (Core Class Expansions)
- Category: game-mechanic
- Created: 2026-04-07
- DB sections: apg/ch02/Core Class Expansions
- Depends on: dc-cr-class-alchemist, dc-cr-class-barbarian, dc-cr-class-bard, dc-cr-class-champion, dc-cr-class-cleric, dc-cr-class-druid, dc-cr-class-fighter, dc-cr-class-monk, dc-cr-class-ranger, dc-cr-class-rogue, dc-cr-class-sorcerer, dc-cr-class-wizard

## Goal

Load all APG class expansions: new subclasses for existing CRB classes (Barbarian Instincts, Ranger Hunter's Edges, Alchemist Research Fields, Sorcerer Bloodlines, etc.) as additional enum values and class feat entries for those classes.

## Source reference

> "The Advanced Player's Guide expands existing classes with new subclass options and feats." (Advanced Player's Guide, Class Expansions)

## Implementation hint

For each CRB class that gains APG subclass options: add new enum values to the appropriate enum field on the character entity. Barbarian: +Dragon/Fury/Spirit/Superstition Instincts. Alchemist: +Toxicologist Research Field. Ranger: +Outwit Hunter's Edge. Sorcerer: +Imperial Bloodline. Each new subclass also comes with class feats tagged to that subclass via `prerequisite: {class_feature: 'instinct_X'}`. Load all as feat entities. No new services required — existing class systems handle the new subclass values via enum extension.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; subclass selection requires character ownership (`_character_access: TRUE`)
- CSRF expectations: all POST/PATCH class subclass routes require `_csrf_request_header_mode: TRUE`
- Input validation: all new subclass enum values validated server-side; prerequisite feat chains validated for new class feats
- PII/logging constraints: no PII logged; character id + class id + subclass id only

## Roadmap section
- Book: apg, Chapter: ch02
- REQs: 191–221 (31 REQs, Core Class Expansions section)
- See `runbooks/roadmap-audit.md` for audit process.
