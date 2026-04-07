# Feature Brief: Recall Knowledge Skill Action

- Work item id: dc-cr-skills-recall-knowledge
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P1
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch04
- Category: skill-action
- Created: 2026-04-07
- DB sections: core/ch04/Occultism (Int), core/ch04/Religion (Wis)
- Depends on: dc-cr-skill-system, dc-cr-creature-identification, dc-cr-dc-rarity-spell-adjustment

## Goal

Implement the Recall Knowledge action across all applicable skills (Arcana/Nature/Occultism/Religion/Society/Crafting/Lore), routing creature/topic type to the correct skill and resolving against a DC set by creature level or topic rarity.

## Source reference

> "You attempt a skill check to recall a bit of knowledge about a topic." (Chapter 4: Skills, PF2E Core Rulebook)

## Implementation hint

`RecallKnowledgeResolver::resolve(character, target_id, skill_hint)` → determines correct skill from target type (e.g., construct → Arcana, undead → Religion, dragon → Arcana/Nature), resolves check vs DC = 10 + creature level or 15 + item level, returns `knowledge_tier` (crit success = all abilities/immunities/weaknesses, success = most, failure = name only, crit fail = wrong info). Untrained allowed for most except specialized topics. Result cached on encounter entity to prevent re-rolling until new information obtained.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; Recall Knowledge requires character ownership (`_character_access: TRUE`)
- CSRF expectations: all POST/PATCH skill/recall-knowledge routes require `_csrf_request_header_mode: TRUE`
- Input validation: target id validated as valid creature/item/topic entity; skill id validated against allowed set for target type; DC sourced server-side from target level
- PII/logging constraints: no PII logged; character id + target id + skill id + knowledge tier only

## Roadmap section
- Book: core, Chapter: ch04, ch10
- REQs: 1591, 1592, 1593, 1594, 2329
- See `runbooks/roadmap-audit.md` for audit process.
