# Feature Brief: Core Book Chapter 6 — Complete Equipment Rules

- Work item id: dc-cr-equipment-ch06
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch06
- Category: equipment
- Created: 2026-04-07
- DB sections: core/ch06/Adventuring Gear, core/ch06/Alchemical Gear (1st-Level Access), core/ch06/Animals and Mounts, core/ch06/Armor, core/ch06/Carrying and Item Rules, core/ch06/Class Starting Kits (Reference), core/ch06/Currency and Economy, core/ch06/Formulas, core/ch06/Magical Gear (1st-Level Access), core/ch06/Services and Economy, core/ch06/Shields, core/ch06/Weapons
- Depends on: dc-cr-equipment-system

## Goal

Implement the equipment catalog content type and data for all Chapter 6 items: weapons (with damage dice, traits, range), armor (with AC bonus, Dex cap, penalties, strength req), shields (hardness/HP/BT), and general gear, with bulk and pricing.

## Source reference

> "Weapons, armor, and other equipment can be found in Chapter 6." (Chapter 6: Equipment, PF2E Core Rulebook)

## Implementation hint

Item content type: `name`, `price{gp,sp,cp}`, `bulk`, `hands`, `level`, `traits[]`, `item_type` enum (weapon/armor/shield/consumable/held/worn/other). Weapon extra fields: `damage_dice`, `damage_type`, `range`, `reload`. Armor extra: `ac_bonus`, `dex_cap`, `check_penalty`, `speed_penalty`, `strength_req`. Shield extra: `hardness`, `hp`, `break_threshold`. `EquipmentService::equip(character, item_id, slot)` validates slot/proficiency compatibility. Character entity gains `equipment{slots}` mapping. `BulkCalculator` reads item bulk from entity.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; equip/unequip requires character ownership (`_character_access: TRUE`)
- CSRF expectations: all POST/PATCH equipment routes require `_csrf_request_header_mode: TRUE`
- Input validation: item id validated against equipment catalog; slot type validated against item_type; strength requirement enforced server-side on equip
- PII/logging constraints: no PII logged; character id + item id + slot + action type only

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
