# QA Suite Activation: dc-cr-skills-crafting-actions

- Status: done
- Summary: Activated `dc-cr-skills-crafting-actions` suite. Added `dc-cr-skills-crafting-actions-phpunit` to `suite.json` with 30 TCs total — 14 immediately activatable and 16 deferred pending `dc-cr-equipment-system` (in-progress Release B). Activatable TCs cover: Repair proficiency gate (TC-CRF-01) and activity type (TC-CRF-03); Craft proficiency gate (TC-CRF-09), downtime activity type (TC-CRF-10), item-level cap vs character level (TC-CRF-12), item-level 9+/16+ proficiency caps (TC-CRF-13–14), and feat gates for alchemical/magical/snare crafting (TC-CRF-23–25); Identify Alchemy proficiency gate (TC-CRF-26), activity type (TC-CRF-28), and Critical Failure false-identification behavior (TC-CRF-29); ACL regression (TC-CRF-30). Deferred TCs (TC-CRF-02,04–08,11,15–22,27) all depend on equipment system item HP, Hardness, formula catalog, material cost, downtime tracking, and tool inventory. ACL regression rule added to `qa-permissions.json`. Suite validated OK. Committed `0b694f85e`.

## Verification evidence

| Item | Result |
|---|---|
| Suite id added | `dc-cr-skills-crafting-actions-phpunit` |
| Total TCs | 30 (14 immediately activatable, 16 deferred) |
| required_for_release TCs | 14 (deferred TCs set false) |
| qa-permissions.json rule | `dc-cr-skills-crafting-actions-acl-regression` |
| Suite validate | OK (5 manifests) |
| Commit | `0b694f85e` |

## PM notes flagged

1. **TC-CRF-08 Repair full-HP no-op:** AC says "no-op or minimal effect per GM." For automation, recommend defining as 0 HP restored with no error (not a UI warning). PM to confirm.
2. **TC-CRF-15 material cost tracking:** AC says "≥50% raw material cost upfront" but does not specify whether cost is tracked as currency or inventory entries. Affects how the check is parameterized. PM to confirm.
3. **Deferred sequencing:** 16 TCs covering Repair outcomes (HP/Hardness/destroy), Craft formula/cost/downtime/batch logic, and Identify Alchemy tool check cannot activate until `dc-cr-equipment-system` ships. Recommend Crafting actions outcome verification be scoped to a follow-on QA cycle after equipment system lands.

## Deferred TCs summary

| TC | Dependency | Reason |
|---|---|---|
| TC-CRF-02 | `dc-cr-equipment-system` | Repair kit inventory presence check |
| TC-CRF-04–05 | `dc-cr-equipment-system` | Repair HP restoration formula (Success/Crit Success) |
| TC-CRF-06 | `dc-cr-equipment-system` | Repair Crit Fail: item takes 2d6 after Hardness |
| TC-CRF-07 | `dc-cr-equipment-system` | Repair blocked on destroyed item state |
| TC-CRF-08 | `dc-cr-equipment-system` | Repair full-HP no-op check |
| TC-CRF-11 | `dc-cr-equipment-system` | Craft: formula catalog/ownership check |
| TC-CRF-15 | `dc-cr-equipment-system` | Craft: ≥50% upfront material cost gate |
| TC-CRF-16–17 | `dc-cr-equipment-system` | Craft: 4-day minimum + pause/resume downtime tracking |
| TC-CRF-18–20 | `dc-cr-equipment-system` | Craft degrees of success: cost rates + material salvage/loss |
| TC-CRF-21–22 | `dc-cr-equipment-system` | Craft consumable/ammunition batch logic |
| TC-CRF-27 | `dc-cr-equipment-system` | Identify Alchemy: alchemist's tools inventory check |

## Next actions
- Awaiting Dev implementation; run `dc-cr-skills-crafting-actions-phpunit` when implementation ships.
- Deferred TCs (16) to activate when `dc-cr-equipment-system` ships.

## Blockers
- None

## ROI estimate
- ROI: 17
- Rationale: 14 activatable TCs unblock Dev on all proficiency/level/feat gate logic for Crafting; clear deferred boundary with equipment system prevents future blocker loops on outcome TCs.

---
- Agent: qa-dungeoncrawler
- Source inbox: sessions/qa-dungeoncrawler/inbox/20260410-170756-suite-activate-dc-cr-skills-crafting-actions
- Generated: 2026-04-11
