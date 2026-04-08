# Outbox: Suite Activation — dc-cr-class-alchemist

- Status: done
- Summary: Activated `dc-cr-class-alchemist-e2e` suite entry in `qa-suites/products/dungeoncrawler/suite.json` with 44 PHPUnit TCs (TC-ALC-01–044), all tagged `"feature_id": "dc-cr-class-alchemist"` and `"required_for_release": true`. Coverage spans: class identity (INT key ability, class DC, description content, HP = 8+CON), research field selection/lock/validation (Bomber/Chirurgeon/Mutagenist), field-specific alchemy item production, infused reagent count formula (level+INT mod, minimum 1), refresh at daily prep, consumption by Advanced/Quick Alchemy, zero-reagent guard, Advanced Alchemy (daily prep creation from formula book, level cap, expiry — afflictions persist), Quick Alchemy (1-action 1-reagent create, next-turn expiry, level cap, formula-book gate), formula book (starting formulas per field, addable), level-gated features at levels 5 (Field Discovery, Powerful Alchemy), 7 (Perpetual Infusions), 9 (Double Brew), 11 (Perpetual Potency), 13 (Greater Field Discovery per field), 15 (Alchemical Alacrity), 17 (Perpetual Perfection), no-early-feature guard, feat progression schedule (class feat L1+even, skill feat L2+every-2), additive feats (one per item, second spoils, infused-only gate, combined level cap), Chirurgeon 10-min immunity, cross-player 403 guard, and regression audit pass. Suite uses PHPUnit (not Playwright) — no new routes; security AC exemption confirmed; `qa-permissions.json` not updated. Dependency note in entry: `dc-cr-equipment-system` must be stable for item-creation TCs. Dedup applied (prior 40-TC partial write removed). Suite validates OK (49 entries, 5 manifests). Committed as `a9294627f`.

## Next actions
- Await next inbox dispatch for additional Release-C suite activations or Gate 2 verification work.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 10
- Rationale: Alchemist is the most mechanically complex class in this release with 44 TCs covering level-gated features, multi-field variants, and reagent economy; complete coverage now prevents cascading Gate 2 failures when Dev implements all 44 AC items.
