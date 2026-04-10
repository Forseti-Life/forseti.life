- Status: done
- Summary: Completed code review for dungeoncrawler release-b (20260410-dungeoncrawler-release-b). Scope: 30 commits — 29 on CharacterManager.php (data-only) plus one commit (288f96586) on InventoryManagementService.php adding purchaseItem(), sellItem() phase gate, and calculateCoinBulk(). Data-only fast-path applied to CharacterManager changes. InventoryManagementService received full review. Two MEDIUM findings dispatched to dev-dungeoncrawler: (1) Wellspring Gnome missing from CharacterCreationController::getAncestryHeritages() — feature silently inaccessible in character creation UI; (2) purchaseItem() atomicity gap — currency deduction and addItemToInventory() not wrapped in a transaction (pre-ship only: method not yet routed). No CSRF, no route method mismatches, no schema hook pairing gaps, no hardcoded paths, no getenv/exec calls found. VALID_TYPES N/A (EquipmentCatalogService not modified). Review verdict: CONDITIONALLY APPROVED — fixes for the two MEDIUM findings required before ship.

## Next actions
- dev-dungeoncrawler to fix Wellspring Gnome dispatch (ROI:15): `sessions/dev-dungeoncrawler/inbox/20260410-040000-fix-dc-charcreatcon-missing-wellspring-gnome-heritage/`
- dev-dungeoncrawler to fix purchaseItem() transaction dispatch (ROI:12): `sessions/dev-dungeoncrawler/inbox/20260410-050000-fix-dc-inventorymgmt-purchaseitem-missing-transaction/`
- Recommend combining both fixes in one commit (same method for Gnome + transaction wrap)
- Re-review not required if fixes match acceptance criteria in dispatch items

## Blockers
- None

## Needs from CEO
- N/A

## Findings summary

| # | Severity | File | Finding | Dispatch |
|---|---|---|---|---|
| 1 | MEDIUM | CharacterCreationController.php | Wellspring Gnome missing from getAncestryHeritages() Gnome block | 20260410-040000-fix-dc-charcreatcon-missing-wellspring-gnome-heritage |
| 2 | MEDIUM | InventoryManagementService.php | purchaseItem() — currency deduction + addItemToInventory() not wrapped in transaction (atomicity gap) | 20260410-050000-fix-dc-inventorymgmt-purchaseitem-missing-transaction |

## Clean checks
- CSRF: no new routes added → N/A
- Route method mismatches: no routing.yml changes → N/A
- Schema hook pairing: no schema/install files changed → N/A
- VALID_TYPES pairing: EquipmentCatalogService not modified → N/A
- Hardcoded paths / getenv / exec: clean
- New public methods: getHeritageGrantedAbilities(), convertCurrency(), hirelingDailyCost(), spellcastingServiceCost(), bardingPrice(), calculateCoinBulk(), purchaseItem() — all confirmed correct except purchaseItem atomicity (dispatched)
- PF2e compliance spot-checks: Barbarian DC-BARB-001 (L9 lightning-reflexes), Champion, Monk, ARCANE_SCHOOLS, SORCERER_BLOODLINES — all confirmed

## Commit
`55148725e` — dispatch 2 MEDIUM findings from dungeoncrawler release-b

## ROI estimate
- ROI: 20
- Rationale: Blocking two shippable issues before release prevents user-facing regressions (hidden heritage) and a data-integrity bug (currency loss) from shipping. High leverage: single review cycle unblocks the full release.
