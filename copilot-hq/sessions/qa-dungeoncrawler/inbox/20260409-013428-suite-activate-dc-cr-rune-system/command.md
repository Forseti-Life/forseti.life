# Suite Activation: dc-cr-rune-system

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-09T01:34:29+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-rune-system"`**  
   This links the test to the living requirements doc at `features/dc-cr-rune-system/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-rune-system-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-rune-system",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-rune-system"`**  
   Example:
   ```json
   {
     "id": "dc-cr-rune-system-<route-slug>",
     "feature_id": "dc-cr-rune-system",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-rune-system",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-rune-system

## Coverage summary
- AC items: 21 (17 happy path, 2 edge cases, 2 failure modes)
- Test cases: 22 (TC-RUN-01–22)
- Suites: playwright (inventory/crafting/character creation)
- Security: AC exemption granted (no new routes)
- Note: These tests EXTEND dc-cr-magic-ch11 coverage; rune system is a standalone subsystem feature.

---

## TC-RUN-01 — Weapon potency runes: attack bonus and slot unlock
- Description: +1/+2/+3 weapon potency runes each grant attack bonus and unlock property rune slots equal to potency value
- Suite: playwright/inventory
- Expected: potency=1 → attack_bonus +1, property_slots = 1; potency=2 → +2, slots = 2; potency=3 → +3, slots = 3
- AC: Fundamental-1

## TC-RUN-02 — Striking runes: damage dice count
- Description: Striking → 2d, Greater Striking → 3d, Major Striking → 4d (of weapon's damage die)
- Suite: playwright/inventory
- Expected: striking=Striking: damage_dice = 2; Greater: 3; Major: 4
- AC: Fundamental-2

## TC-RUN-03 — Armor potency runes: AC bonus and slot unlock
- Description: +1/+2/+3 armor potency runes grant item bonus to AC and unlock property rune slots
- Suite: playwright/inventory
- Expected: armor potency=1 → AC_item_bonus +1, property_slots = 1; potency=2 → +2, slots = 2; potency=3 → +3, slots = 3
- AC: Fundamental-3

## TC-RUN-04 — Resilient runes: save bonuses
- Description: Resilient → +1 saves, Greater Resilient → +2, Major Resilient → +3 (item bonus)
- Suite: playwright/inventory
- Expected: resilient=Resilient: all_saves_item_bonus = +1; Greater: +2; Major: +3
- AC: Fundamental-4

## TC-RUN-05 — Property rune slots require potency rune
- Description: Without a potency rune, zero property rune slots available
- Suite: playwright/inventory
- Expected: weapon.potency_rune = none → property_slots = 0; etching property rune blocked
- AC: Property-1, Edge-1

## TC-RUN-06 — Duplicate property runes: higher-level wins
- Description: Two identical property runes on same item — only higher-level applies
- Suite: playwright/inventory
- Expected: duplicate_property_rune → item uses the higher-level version; lower is dormant
- AC: Property-2

## TC-RUN-07 — Energy-resistant runes stack by damage type
- Description: Multiple energy-resistant property runes apply if each covers a different energy type
- Suite: playwright/inventory
- Expected: fire_resistance + cold_resistance both active simultaneously; same-type duplicate follows duplicate rule
- AC: Property-2

## TC-RUN-08 — Orphaned property rune goes dormant
- Description: Removing potency rune makes property runes dormant; reactivates when compatible potency restored
- Suite: playwright/inventory
- Expected: remove potency → property_rune.state = dormant; re-equip potency → property_rune.state = active
- AC: Property-3, Failure-2

## TC-RUN-09 — Etch a Rune: requirements gate
- Description: Requires Magical Crafting feat, formula, item in possession; one rune at a time
- Suite: playwright/crafting
- Expected: attempt without Magical Crafting feat → blocked; without formula → blocked; with all → starts craft activity
- AC: Etching-1

## TC-RUN-10 — Etch a Rune: downtime activity
- Description: Etch a Rune is a downtime Craft activity
- Suite: playwright/crafting
- Expected: etch action phase = downtime; cannot be triggered in encounter or exploration
- AC: Etching-1

## TC-RUN-11 — Transfer Rune: DC, cost, and duration
- Description: Transfer Rune DC = rune level DC; cost = 10% of rune price; minimum 1 day
- Suite: playwright/crafting
- Expected: transfer.DC = rune_level_dc; transfer.cost = rune.price × 0.10; transfer.min_duration = 1 day
- AC: Etching-2

## TC-RUN-12 — Transfer from runestone: free
- Description: Transferring a rune from a runestone has no cost
- Suite: playwright/crafting
- Expected: transfer.source = runestone → cost = 0
- AC: Etching-3

## TC-RUN-13 — Incompatible rune transfer: auto-crit fail
- Description: Attempting to transfer incompatible rune results in automatic critical failure and no cost charged
- Suite: playwright/crafting
- Expected: incompatible transfer → result = critical_failure; cost not deducted
- AC: Etching-4, Failure-1

## TC-RUN-14 — Same-category swaps only
- Description: Fundamental runes can only swap with fundamental; property with property
- Suite: playwright/crafting
- Expected: fundamental-to-property swap attempt → blocked (incompatible category)
- AC: Etching-5

## TC-RUN-15 — Item upgrade Crafting cost formula
- Description: Upgrade cost = new rune price minus existing rune price; DC uses new rune level
- Suite: playwright/crafting
- Expected: upgrade_cost = new_rune.price − old_rune.price; craft_dc = new_rune_level_dc
- AC: Etching-6

## TC-RUN-16 — One precious material per item
- Description: Items can have at most one precious material
- Suite: playwright/inventory
- Expected: attempt to add second material to item → blocked
- AC: Materials-1

## TC-RUN-17 — Material grade requirements: Low/Standard/High
- Description: Low = Expert Crafting, max level 8; Standard = Master Crafting, max level 15; High = Legendary Crafting
- Suite: playwright/crafting
- Expected: Low grade blocked if Crafting < Expert or item.level > 8; Standard blocked if < Master or level > 15; High blocked if < Legendary
- AC: Materials-2

## TC-RUN-18 — Material investment percentages
- Description: Low = 10% of initial cost, Standard = 25%, High = 100%
- Suite: playwright/crafting
- Expected: material_investment_cost = base_item_cost × grade_percentage
- AC: Materials-3

## TC-RUN-19 — Material Hardness/HP/BT values
- Description: All listed materials (Adamantine, Cold Iron, Darkwood, Dragonhide, Mithral, Orichalcum, Silver) have correct H/HP/BT per grade
- Suite: playwright/inventory
- Expected: each material × each grade combination returns correct hardness, hp, bt values per table
- AC: Materials-4

## TC-RUN-20 — Special material properties
- Description: Cold iron, adamantine, darkwood, dragonhide, mithral, orichalcum, silver implement their special properties
- Suite: playwright/encounter
- Expected: each material's special property triggers correctly (e.g., adamantine ignores hardness of objects; cold iron disrupts fey regeneration)
- AC: Materials-5

## TC-RUN-21 — Specific locked magic items: no property rune slots
- Description: Items with 0 locked property slots only allow fundamental runes
- Suite: playwright/inventory
- Expected: locked_item.property_slots = 0; only potency and striking/resilient runes can be etched
- AC: Edge-2

## TC-RUN-22 — Orichalcum: fixed 4 property rune slots (material override)
- Description: Orichalcum overrides normal property slot formula; always grants 4 property rune slots regardless of potency value
- Suite: playwright/inventory
- Expected: orichalcum_weapon.property_slots = 4 (not equal to potency rune value)
- Dev NOTE: This is a material-level override, not a potency rune formula result. Dev must implement Orichalcum as a special case that bypasses the slots = potency_value formula. See also TC-MCH-36 in dc-cr-magic-ch11.
- AC: Materials-4 (Orichalcum special case)

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-rune-system

## Gap analysis reference
- DB sections: core/ch11/Runes (19 reqs) and core/ch11/Precious Materials (18 reqs)
  Note: These were already covered in dc-cr-magic-ch11 and flipped to in_progress.
  This feature focuses on the rune system as a distinct subsystem (pre-ch11 feature stub).
- Depends on: dc-cr-equipment-system ✓, dc-cr-magic-system

---

## Happy Path

### Fundamental Runes
- [ ] `[EXTEND]` Weapon potency runes: +1, +2, +3 — each adds attack bonus and unlocks property rune slots equal to potency value.
- [ ] `[EXTEND]` Striking runes: Striking (2d), Greater Striking (3d), Major Striking (4d) — increases weapon damage dice count.
- [ ] `[EXTEND]` Armor potency runes: +1, +2, +3 — each adds item bonus to AC and unlocks property rune slots.
- [ ] `[EXTEND]` Resilient runes: Resilient (+1 saves), Greater Resilient (+2), Major Resilient (+3).

### Property Runes
- [ ] `[EXTEND]` Property rune slots = potency rune value (0 slots without potency rune).
- [ ] `[EXTEND]` Duplicate property runes: only higher-level applies (exception: energy-resistant, different damage types all apply).
- [ ] `[EXTEND]` Orphaned property runes (potency rune removed): go dormant until compatible potency rune present.
- [ ] `[EXTEND]` Energy-resistant property runes can stack if each is a different energy type.

### Etching and Transferring Runes
- [ ] `[EXTEND]` Etch a Rune: Craft activity (downtime); requires Magical Crafting feat, formula, item in possession; one rune at a time.
- [ ] `[EXTEND]` Transfer Rune: Craft activity; DC by rune level; cost = 10% of rune price; minimum 1 day.
- [ ] `[EXTEND]` Transfer from runestone: free (no cost).
- [ ] `[EXTEND]` Incompatible rune transfer: automatic critical failure.
- [ ] `[EXTEND]` Only same-category swaps: fundamental ↔ fundamental, property ↔ property.
- [ ] `[EXTEND]` Item upgrade Crafting: cost = (new rune price) – (existing rune price); DC uses new rune level.

### Precious Materials
- [ ] `[EXTEND]` Items have at most one precious material.
- [ ] `[EXTEND]` Material grades: Low (Expert Crafting, max level 8), Standard (Master Crafting, max level 15), High (Legendary Crafting, no restriction).
- [ ] `[EXTEND]` Investment percentages: Low = 10%, Standard = 25%, High = 100% of initial cost.
- [ ] `[EXTEND]` All material Hardness/HP/BT values implemented (Adamantine, Cold Iron, Darkwood, Dragonhide, Mithral, Orichalcum, Silver, plus base materials).
- [ ] `[EXTEND]` Cold iron, adamantine, darkwood, dragonhide, mithral, orichalcum: special material properties implemented per dc-cr-magic-ch11.

---

## Edge Cases
- [ ] `[EXTEND]` Rune slots blocked without potency rune: property runes cannot be etched.
- [ ] `[EXTEND]` Specific locked magic items (0 property slots): only fundamental runes allowed.

## Failure Modes
- [ ] `[TEST-ONLY]` Incompatible rune transfer: auto-crit fail (does not charge cost).
- [ ] `[TEST-ONLY]` Orphaned property rune: dormant, not deleted; reactivates when compatible potency present.

## Security acceptance criteria
- Security AC exemption: game-mechanic rune and material system; no new routes or user-facing input beyond existing character creation and inventory management forms
