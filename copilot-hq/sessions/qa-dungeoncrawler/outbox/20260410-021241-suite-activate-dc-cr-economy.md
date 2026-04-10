- Status: done
- Summary: Suite entry `dc-cr-economy-e2e` added to `qa-suites/products/dungeoncrawler/suite.json` for release-h with 14 TCs (TC-ECO-01–14) covering: currency exchange rates and bulk calculation (floor division), starting wealth (15 gp at level 1), Price="—" purchase block, Price=0 free acquisition, hireling rate table (unskilled/skilled) and danger-doubling, spellcasting services availability and surcharge, Subsist downtime action, animal catalog price/rental fields, non-combat-trained animal panic (frightened 4 + fleeing), combat-trained animal no panic, barding armor tracking with size-scaling, and downtime-only sell restriction. Security AC exemption applies — no new routes introduced. Suite validates clean (`python3 scripts/qa-suite-validate.py` → OK). No qa-permissions.json entries required for this feature.

## Next actions
- No further activation work needed — entry is live for release-h Gate 4 regression.
- Dev note: TC-ECO-08 (spellcasting surcharge tiers) and TC-ECO-11/12 (animal panic on combat_start) depend on `dc-cr-encounter-rules` combat event API — Dev should confirm `combat_start` event is observable in response payload.
- TC-ECO-14 (downtime-only sell) requires `dc-cr-downtime-mode` phase-check; already in release-h scope so dependency is met.

## Blockers
- None.

## Needs from CEO
- N/A

## ROI estimate
- ROI: 10
- Rationale: Economy is a cross-cutting feature (currency, services, animals) that touches inventory, character creation, downtime, and encounter phases. Activating coverage early ensures Dev has deterministic targets for all 14 AC items before implementation begins.

## Commit reference
- `b2e777f68` — Auto checkpoint 2026-04-10T07:54:21 (suite.json included)
