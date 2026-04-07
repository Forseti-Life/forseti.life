# Feature Brief: Crafting Skill Actions

- Work item id: dc-cr-skills-crafting-actions
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
- DB sections: core/ch04/Crafting (Int)
- Depends on: dc-cr-skill-system, dc-cr-equipment-ch06, dc-cr-economy

## Goal

Implement the Crafting skill actions: Craft (Trained, downtime item creation), Identify Magic (Trained, determine item properties), Recall Knowledge (Untrained), and Repair (Trained), with full DC/cost tables.

## Source reference

> "Crafting measures your skill at creating, repairing, and modifying items." (Chapter 4: Skills, PF2E Core Rulebook)

## Implementation hint

`CraftingActionResolver`: Craft (downtime): validates character has formula; resolves vs item DC (by level); success = item completed after `days_needed = 4 + item_level`; characters may expend extra days for gold reduction (at half raw material rate). Repair: Crafting vs DC 10 + item level; restores BT/HP of damaged item. Identify Magic: 10-minute activity, DC = 20 + spell/item level. Recall Knowledge: DC by item type. `CraftingService::craft(character, formula_id, days, raw_material_gold)` is the primary entry point; handles material cost deduction + item grant atomically.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; crafting actions require character ownership (`_character_access: TRUE`)
- CSRF expectations: all POST/PATCH skill/crafting routes require `_csrf_request_header_mode: TRUE`
- Input validation: formula id validated against character's formula book; material gold deduction and item grant atomic (no partial state); item id for Repair validated as character-owned damaged item
- PII/logging constraints: no PII logged; character id + item id + gold delta + outcome only

## Roadmap section
- Book: core, Chapter: ch04, ch10
- REQs: 1644 (partial), 1645–1656 (Craft/Repair/Identify Alchemy actions), 2325
- See `runbooks/roadmap-audit.md` for audit process.
