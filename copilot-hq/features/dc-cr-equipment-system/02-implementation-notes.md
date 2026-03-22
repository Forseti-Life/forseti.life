# Implementation Notes (Dev-owned)
# Feature: dc-cr-equipment-system

## Summary
EXTEND: `InventoryManagementService` (inventory CRUD, bulk, encumbrance) is complete. `ItemCombatDataService` and `content/items/` catalog are partial — only `healing_potion_minor.json` and `longsword.json` confirmed present. No `GET /equipment` route exists. No starting equipment endpoint. First slice: audit and extend `content/items/` to meet AC minimums (5 simple weapons, 5 martial weapons, 3 light armors, 2 medium armors, 1 heavy armor, 10 gear) + implement `GET /equipment?type=` route.

## Impact Analysis
- `content/items/` — JSON catalog additions; no PHP changes required for catalog.
- New route `GET /equipment` in `dungeoncrawler_content.routing.yml` + new `EquipmentController`.
- `GET /classes/{id}/starting-equipment` is slice 2 (depends on character_class content type from dc-cr-character-class).
- Equip → stat integration is slice 3 (verify existing `InventoryManagementService` equipped tracking).
- Existing `longsword.json` must be verified against AC weapon schema (damage, traits, bulk, price, group) — adjust if incomplete.

## Files / Components Touched
- `dungeoncrawler_content/content/items/` — add weapon/armor/gear JSON files per AC minimums
- `dungeoncrawler_content/dungeoncrawler_content.routing.yml` — add `dungeoncrawler_content.equipment_list` route: `GET /equipment`
- `dungeoncrawler_content/src/Controller/EquipmentController.php` — new controller with `listEquipment(Request $request)`, filters by `?type=` query param
- `dungeoncrawler_content/src/Service/ItemCombatDataService.php` — audit and extend weapon/armor stat data if incomplete

## Data Model / Storage Changes
- Schema updates: none (JSON file catalog loaded at runtime)
- Config changes: new route in routing.yml
- Migrations: none

## First code slice
1. Audit `content/items/` — list all files, check weapon/armor schema completeness.
2. Add missing weapon items: shortsword, dagger, rapier, handaxe, greataxe (to reach 5 simple + 5 martial mix).
3. Add armor items: leather, studded leather, explorer's clothing (light), chain mail, scale mail (medium), full plate (heavy).
4. Add gear: rope, torch, bedroll, rations, waterskin, lantern, oil, crowbar, grappling hook, thieves' tools.
5. Implement `GET /equipment?type=` route + controller.

## Security Considerations
- Input validation: `type` query param must be one of: weapon, armor, shield, gear; reject unknown values with 400.
- Access checks: equipment catalog is public (anonymous read per AC).
- Sensitive data handling: none.

## Testing Performed
- Commands run: (pending implementation)
- Targeted scenarios:
  - `curl http://localhost:8080/equipment?type=weapon` → array of weapons with damage, traits, bulk
  - `curl http://localhost:8080/equipment?type=armor` → array of armors with AC_bonus, max_dex_bonus
  - Unknown type → 400
  - Anonymous access → 200

## Rollback / Recovery
- Revert commit. JSON files are content/data; no DB schema changes.

## Knowledgebase references
- `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md`
- `docs/dungeoncrawler/INVENTORY_MANAGEMENT_SYSTEM.md` — existing design doc per AC KB note.

## What I learned (Dev)
- (pending)

## What I'd change next time (Dev)
- (pending)
