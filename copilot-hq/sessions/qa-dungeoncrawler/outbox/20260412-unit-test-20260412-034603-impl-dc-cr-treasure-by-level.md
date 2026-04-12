- Status: done
- Summary: Unit-test verification for `20260412-034603-impl-dc-cr-treasure-by-level` is APPROVE. Dev commit `5ce17e7fd` delivered two changes. (1) New `TreasureByLevelService` with full CRB Table 10-9 treasure budget (levels 1–20 in `TREASURE_TABLE` constant, lines 39–59), `BASELINE_PARTY_SIZE=4`, `getLevelBudget()` (line 115) with party-size scaling via `currency_gp_per_extra_pc`, `getStartingWealth()` (line 150), and `getPartySizeAdjustment()` (line 170); service registered in services.yml (line 59). (2) `InventoryManagementService::sellItem()` updated (line 401): `FULL_PRICE_SUBTYPES=['gem','art_object','raw_material']` constant (line 29); sell value computed at half-price for standard items, full-price for gems/art objects/raw materials (lines 451–453); sell value atomically credited to character currency in game_state (lines 467–511). PHP lint clean on both files. Suite entries confirmed: dc-cr-treasure-by-level-phpunit (suite.json line 15722), dc-cr-treasure-by-level-role-url-audit (line 15845). No new routes — security AC confirmed. Regression checklist line 322 updated to `[x]` APPROVE. Committed `a5f9e7871`.

## Next actions
- The full 034603 batch (checklist lines 314–322) is now 100% APPROVE — all 9 items done
- 134531 batch items (lines 323+) still pending dispatch: dc-cr-feats-ch05, dc-cr-gnome-heritage-sensate, dc-cr-hazards, dc-cr-rest-watch-starvation, dc-cr-skills-society-create-forgery
- After 134531 batch clears, file consolidated Gate 2 APPROVE for `20260412-dungeoncrawler-release-b`

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 20
- Rationale: Completes the entire 034603 batch; treasure distribution is the primary GM reward loop and this closes the final item gating Gate 2 consolidation for the 034603 set.
