# Feature Brief: Core Book Chapter 11 — Complete Magic Items and Treasure

- Work item id: dc-cr-magic-ch11
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: in_progress
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch11
- Category: equipment
- Created: 2026-04-07
- DB sections: core/ch11/Activating Items, core/ch11/Alchemical Items, core/ch11/Consumables (Magical), core/ch11/Crafting Requirements, core/ch11/Item Rarity, core/ch11/Item Stat Block Format, core/ch11/Item Traits (Notable), core/ch11/Magic Armor, core/ch11/Magic Item Basics, core/ch11/Magic Weapons, core/ch11/Precious Materials, core/ch11/Runes, core/ch11/Shields, core/ch11/Snares, core/ch11/Staves, core/ch11/Wands, core/ch11/Worn Items
- Depends on: dc-cr-magic-items, dc-cr-alchemical-items, dc-cr-rune-system, dc-cr-crafting

## Goal

Implement the magic items system: consumable vs permanent items, rune system (fundamental + property), activation types (Cast a Spell/Command/Envision/Interact), investment limit (10 worn items), and item condition tracking (charged/HP).

## Source reference

> "Magic items are imbued with power that can empower their users with abilities beyond their training." (Chapter 11: Crafting and Treasure, PF2E Core Rulebook)

## Implementation hint

Magic item entity extends base Item with: `item_category` (consumable/permanent), `activation_type` enum (cast/command/envision/interact/none), `charges_max`, `charges_current`, `invested` bool. Investiture: character can invest up to 10 items/day; `InvestmentManager::invest(character, item_id)` validates count ≤ 10 and resets on daily prep. Activation: `MagicItemActivationService::activate(character, item_id, activation_context)` resolves activation type, deducts charge, applies spell/effect. Consumable: destroyed on use. Condition tracking: items have `hp` and `hardness` fields for destruction rules.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; activation and investiture require character ownership (`_character_access: TRUE`)
- CSRF expectations: all POST/PATCH magic-item routes require `_csrf_request_header_mode: TRUE`
- Input validation: activation type validated against item's allowed activation; charge deduction atomic; investment count enforced server-side at exactly 10 maximum
- PII/logging constraints: no PII logged; character id + item id + activation type + charge delta only

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
