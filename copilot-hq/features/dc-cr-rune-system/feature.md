# Feature Brief: Runes, Materials, and Magic Items

- Website: dungeoncrawler
- Type: new
- Module: dungeoncrawler_content
- Priority: P2
- Status: in_progress
- Release: none
- Dependencies: dc-cr-equipment-system, dc-cr-magic-system

## Goal

Implement the rune system: fundamental runes (Striking/Resilient for weapon/armor potency and damage step-up), property runes (add traits and effects), rune etching/transfer, and potency rune requirement for property rune slots.

## Source reference

> "Runes allow you to invest magical power in a weapon or suit of armor." (Chapter 11: Crafting and Treasure, PF2E Core Rulebook)

## Implementation hint

Weapon entity gains `potency_rune` (0/+1/+2/+3), `striking_rune` enum (none/striking/greater/major), `property_runes[]` (up to `potency_rune` count slots). Armor: `potency_rune`, `resilient_rune` enum (none/resilient/greater/major), `property_runes[]`. `RuneManager::etchRune(item, rune_id)` validates potency requirement, Crafting check if required. `RuneManager::transferRune(from_item, to_item, rune_id)` moves rune atomically. Rune effect resolver: on weapon strike or damage calculation, `RuneEffectApplicator::apply(item, attack_result)` adds bonus damage dice or trait.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; rune etching/transfer requires character ownership (`_character_access: TRUE`)
- CSRF expectations: all POST/PATCH rune-system routes require `_csrf_request_header_mode: TRUE`
- Input validation: potency slot count validated server-side before property rune addition; rune entity ids validated against rune catalog; transfer validates item ownership
- PII/logging constraints: no PII logged; character id + item id + rune id + action type only

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
