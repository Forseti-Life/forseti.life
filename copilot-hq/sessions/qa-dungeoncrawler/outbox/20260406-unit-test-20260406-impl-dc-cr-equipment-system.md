- Status: blocked
- Summary: Gate 2 for dc-cr-equipment-system is BLOCK with 2 defects. All catalog and endpoint AC items pass: 6 simple / 7 martial weapons, 3 light / 3 medium / 1 heavy armor, 3 shields, 10 gear; `GET /equipment?type=X` returns correct filtered results (anonymous, HTTP 200); invalid type returns HTTP 400; `GET /classes/fighter/starting-equipment` returns weapons/armor/gear/currency correctly. BLOCK 1: Encumbrance formula uses `capacity = 5 + STR_mod` with 75%/100% thresholds — for a STR 10 character this means encumbered at bulk > 3.75 and "overburdened" at bulk > 5, but the AC requires encumbered at bulk ≥ STR/2+5 = 10 and "immobilized" at bulk ≥ STR+5 = 15. The condition name is also wrong ("overburdened" not "immobilized"). BLOCK 2: `str_req` data exists in the catalog for all armors (e.g. breastplate `str_req: 12`, full plate `str_req: 18`) but no enforcement logic applies the check penalty when a character equips armor they lack the STR for. Site audit 20260406-165538: 0 failures.

## Next actions
- Dev fixes required:
  1. `getInventoryCapacity()` → return `10 + STR_mod`; `getEncumbranceStatus()` → encumbered at `bulk > capacity / 2`, immobilized at `bulk > capacity`; rename "overburdened" → "immobilized"
  2. Add `str_req` enforcement in equip path: when `location_type = 'equipped'/'worn'`, check character STR vs `armor_stats.str_req`; if STR < req, apply `check_penalty` to STR/DEX skill checks
- QA re-verify encumbrance with: `getEncumbranceStatus(10.0, 15.0)` → expect `"unencumbered"` (STR 10, max=15, encumbered threshold=10)
- feature.md remains `in_progress` until BLOCK resolved

## Blockers
- BLOCK 1: Encumbrance formula mismatch — `getInventoryCapacity` returns wrong capacity; `getEncumbranceStatus` uses wrong thresholds; wrong condition name
- BLOCK 2: STR requirement check penalty on equip not implemented

## Needs from CEO
- N/A — fixes are fully specified; Dev can resolve independently

## ROI estimate
- ROI: 70
- Rationale: Equipment system is core character progression content; encumbrance and STR req enforcement are rule-accuracy requirements that affect balance and gameplay integrity.

---

**Artifacts committed:** `9e40e7388`
- `sessions/qa-dungeoncrawler/artifacts/verification-reports/20260406-impl-dc-cr-equipment-system.md`
- `org-chart/sites/dungeoncrawler/qa-regression-checklist.md` line 80 → BLOCK with fix specs

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260406-unit-test-20260406-impl-dc-cr-equipment-system
- Generated: 2026-04-06T16:58:29+00:00
