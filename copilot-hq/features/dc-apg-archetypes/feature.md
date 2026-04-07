# Feature Brief: APG Archetypes System

- Work item id: dc-apg-archetypes
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: in_progress
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Advanced Player's Guide, Chapter 3
- Category: game-mechanic
- Created: 2026-04-07
- DB sections: apg/ch03/Archetype System Rules (General), apg/ch03/Acrobat, apg/ch03/Archaeologist, apg/ch03/Archer, apg/ch03/Assassin, apg/ch03/Bastion, apg/ch03/Beastmaster, apg/ch03/Blessed One, apg/ch03/Bounty Hunter, apg/ch03/Cavalier, apg/ch03/Celebrity, apg/ch03/Dandy, apg/ch03/Dragon Disciple, apg/ch03/Dual-Weapon Warrior, apg/ch03/Duelist, apg/ch03/Eldritch Archer, apg/ch03/Familiar Master, apg/ch03/Gladiator, apg/ch03/Herbalist, apg/ch03/Horizon Walker, apg/ch03/Linguist, apg/ch03/Loremaster, apg/ch03/Marshal, apg/ch03/Martial Artist, apg/ch03/Mauler, apg/ch03/Medic, apg/ch03/Pirate, apg/ch03/Poisoner, apg/ch03/Ritualist, apg/ch03/Scout, apg/ch03/Scroll Trickster, apg/ch03/Scrounger, apg/ch03/Sentinel, apg/ch03/Shadowdancer, apg/ch03/Snarecrafter, apg/ch03/Talisman Dabbler, apg/ch03/Vigilante, apg/ch03/Viking, apg/ch03/Weapon Improviser
- Depends on: dc-cr-multiclass-archetype, dc-cr-character-class, dc-cr-character-leveling

## Goal

Load the ~30 APG archetypes into the feat system, each with a Dedication feat (entry prereq) and archetype follow-on feats, covering class archetypes (replaces class features), multiclass archetypes, and basic archetypes.

## Source reference

> "Archetypes allow you to take on additional character roles or modify your existing class." (Advanced Player's Guide, Archetypes Chapter)

## Implementation hint

Each archetype is a group of feat entities tagged with `archetype_id` FK and `dedication: true` on the entry feat. `ArchetypeManager::getDedicationFeat(archetype_id)` returns the entry requirement. `FeatManager::selectFeat(character, feat_id)` already handles archetype feats via the existing prerequisite chain — no new service needed. APG archetypes include: Archer, Bastion, Bounty Hunter, Cavalier, Dandy, Marshal, Pirate, Poisoner, Ritualist, Sentinel, Viking, and more. Load all as Drupal feat entities with correct prerequisites, traits, and archetype_id grouping.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; archetype feat selection requires character ownership (`_character_access: TRUE`)
- CSRF expectations: all POST/PATCH feat-selection routes require `_csrf_request_header_mode: TRUE`
- Input validation: dedication feat prerequisite chain enforced server-side; archetype feat selection blocked without dedication feat taken first
- PII/logging constraints: no PII logged; character id + feat id + archetype id only

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
