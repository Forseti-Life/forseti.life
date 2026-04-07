# Feature Brief: Creature Identification (Recall Knowledge Routing)

- Work item id: dc-cr-creature-identification
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: in_progress
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch10
- Category: game-mechanic
- Created: 2026-04-07
- DB sections: core/ch10/Creature Identification
- Depends on: dc-cr-skill-system, dc-cr-dc-rarity-spell-adjustment

## Goal

Implement the Identify a Creature special use of Recall Knowledge, routing creature type to the correct skill, returning revealed abilities by success tier, and enforcing the 'no retry until new information' rule.

## Source reference

> "You can attempt to identify a creature with the corresponding skill." (Chapter 9: Playing the Game, PF2E Core Rulebook)

## Implementation hint

Creature type → skill mapping: Aberration/Construct/Dragon/Elemental/Giant/Humanoid → Arcana; Animal/Beast/Fungus/Plant → Nature; Astral/Ethereal/Fey/Fiend/Spirit → Occultism; Undead/Celestial/Daemon/Demon/Devil → Religion. DC = 10 + creature level + rarity modifier. `CreatureIdentificationService::identify(character, creature_id, skill)` resolves check → `IdentificationResult{name, traits, abilities_revealed[]}` where revealed set expands with success tier. Result cached on encounter entity; retry blocked unless new information clue entity added by GM.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; creature identification requires encounter participant validation
- CSRF expectations: all POST/PATCH creature/identification routes require `_csrf_request_header_mode: TRUE`
- Input validation: creature id validated as active encounter participant; skill id validated against allowed mapping for creature type; DC sourced server-side from creature entity
- PII/logging constraints: no PII logged; character id + creature id + skill id + identification tier only

## Roadmap section
- Book: core, Chapter: ch10
- REQs: 2329, 2331
- See `runbooks/roadmap-audit.md` for audit process.
