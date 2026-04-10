# Verification Report — dc-cr-economy

- Status: done
- Summary: Verified dc-cr-economy implementation (dev commits: forseti.life `288f96586`, copilot-hq `3412fe5d3`). All 14 TC-ECO test cases PASS via static analysis of CharacterManager and InventoryManagementService. `CURRENCY_EXCHANGE_RATES` (cp=1/sp=10/gp=100/pp=1000) confirmed; `convertCurrency()` uses rate division. `STARTING_WEALTH_CP = 1500` (15 gp). `calculateCoinBulk()` uses floor division by 1000 (999→0, 1000→1, 1999→1). `purchaseItem()` correctly blocks `price_gp=NULL` (not_for_sale), allows free items (price=0), deducts currency with negative-balance guard (insufficient_funds). Both `purchaseItem()` and `sellItem()` return `downtime_only` for encounter/exploration phases. `HIRELINGS` has unskilled (skill_bonus=0, base=1cp/day, danger=2cp) and skilled (specialty=+4, other=0, base=100cp/day, danger=200cp). `hirelingDailyCost()` returns danger_rate_cp when in_danger=true. `SPELLCASTING_SERVICES` has levels, uncommon surcharge, long-cast surcharge. `SUBSIST_ACTION` success sets subsistence_met=true, cost_cp=0. `ANIMAL_CATALOG` has price_cp and rental_per_day_cp on all entries. `ANIMAL_COMBAT_PANIC` sets condition=frightened/value=4/fleeing=true for non-combat-trained animals; combat-trained entries (warhorse, guard-dog) have combat_trained=TRUE and do not trigger panic. `BARDING_CATALOG` has size_price_multipliers; `bardingPrice()` scales by mount size. Site audit 20260410-105722: 0 violations, 0 permission failures.

## Test results

| TC | Description | Result | Evidence |
|---|---|---|---|
| TC-ECO-01 | Currency exchange rates: 10cp=1sp, 10sp=1gp, 10gp=1pp | PASS | `CURRENCY_EXCHANGE_RATES`: cp=1, sp=10, gp=100, pp=1000; `convertCurrency()` divides by target rate |
| TC-ECO-02 | Items with Price="—" blocked (error=not_for_sale) | PASS | `purchaseItem()` returns not_for_sale when `price_gp === NULL` |
| TC-ECO-03 | Items with Price=0: gold unchanged, item added | PASS | Free-item path: total_price_cp=0 → `addItemToInventory()` skips currency check |
| TC-ECO-04 | Level 1 starting wealth = 15 gp | PASS | `STARTING_WEALTH_CP = 1500` (CharacterManager line 11052) |
| TC-ECO-05 | Coin Bulk: 999→0, 1000→1, 1999→1 | PASS | `calculateCoinBulk()`: `(int) floor($total_coins / 1000)` |
| TC-ECO-06 | Hireling rates: unskilled=+0 all skills; skilled=+4 specialty, +0 elsewhere | PASS | `HIRELINGS['unskilled'].skill_bonus=0`; `HIRELINGS['skilled'].specialty_bonus=4, other_bonus=0` |
| TC-ECO-07 | Hireling daily cost doubles when in_danger=true | PASS | `hirelingDailyCost()` returns `danger_rate_cp` (2× base) when `$in_danger=TRUE` |
| TC-ECO-08 | Spellcasting services: uncommon surcharge + long-cast surcharge | PASS | `SPELLCASTING_SERVICES` with `surcharge_uncommon_cp` and `surcharge_long_cast_cp` keys |
| TC-ECO-09 | Subsist action: success sets subsistence_met=true; no gold deducted | PASS | `SUBSIST_ACTION['success'] = ['subsistence_met' => TRUE, 'cost_cp' => 0]` |
| TC-ECO-10 | Animal catalog: price and rental_per_day fields present | PASS | All `ANIMAL_CATALOG` entries have `price_cp` and `rental_per_day_cp` |
| TC-ECO-11 | Non-combat-trained animal: frightened=4 + fleeing=true on combat_start | PASS | `ANIMAL_COMBAT_PANIC`: condition=frightened, value=4, fleeing=TRUE |
| TC-ECO-12 | Combat-trained animal: no frightened/fleeing on combat_start | PASS | Warhorse/guard-dog entries have `combat_trained=TRUE`; panic does not apply |
| TC-ECO-13 | Barding: rune_slots=0, price and bulk scale with mount.size | PASS | `bardingPrice()` uses `size_price_multipliers`; BARDING_CATALOG entries have `rune_slots=0` per AC |
| TC-ECO-14 | Sell/purchase during encounter or exploration: error=downtime_only | PASS | Both `sellItem()` and `purchaseItem()` return `downtime_only` for encounter/exploration phase |

## Site audit
- Audit ID: 20260410-105722
- Result: 0 violations, 0 permission expectation failures, 0 404s
- Note: No new routes added by this implementation; all economy logic is service/data-layer only

## Next actions
- No new Dev items identified
- PM may proceed to next release gate step

## Blockers
- None

## ROI estimate
- ROI: 20
- Rationale: Economy system is a shared foundation used by every item, equipment, and downtime feature; APPROVE unblocks follow-on shop/hireling/barding features that depend on this data layer.
