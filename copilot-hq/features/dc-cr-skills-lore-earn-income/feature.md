# Feature Brief: Lore and Earn Income Skill Actions

- Work item id: dc-cr-skills-lore-earn-income
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch04
- Category: skill-action
- Created: 2026-04-07
- DB sections: core/ch04/Lore (Int)
- Depends on: dc-cr-skill-system, dc-cr-economy, dc-cr-dc-rarity-spell-adjustment

## Goal

Implement the Lore skill's Earn Income downtime activity and Recall Knowledge action, where Lore topics are character-specific narrow specialties and Earn Income lets characters generate GP over days of work.

## Source reference

> "Lore represents a swathe of specialized knowledge that you've picked up during your life." (Chapter 4: Skills, PF2E Core Rulebook)

## Implementation hint

Lore is a family of skills, not a single one; character entity has `lore_topics[]` list (e.g., 'Sailing Lore', 'Scribing Lore'). Earn Income: downtime activity; DC by level (from table); check against Lore, Crafting, or Performance; success = GP per day at that DC's income tier (from table); critical success = next tier; failure = half; critical failure = 0. `EarnIncomeService::resolve(character, lore_topic, days, settlement_level)` → `{gp_earned, income_tier}`. Recall Knowledge: uses Lore topic as the skill; DC set by creature level or topic difficulty.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; Earn Income and Recall Knowledge require character ownership (`_character_access: TRUE`)
- CSRF expectations: all POST/PATCH skill/lore routes require `_csrf_request_header_mode: TRUE`
- Input validation: lore topic validated against character's lore list; income DC sourced from server-side level table; days worked validated as positive integer
- PII/logging constraints: no PII logged; character id + lore topic + gp delta + dc only

## Roadmap section
- Book: core, Chapter: ch04, ch10
- REQs: 1579, 1580, 1581, 1582, 1685, 1686, 1687, 2326
- See `runbooks/roadmap-audit.md` for audit process.
