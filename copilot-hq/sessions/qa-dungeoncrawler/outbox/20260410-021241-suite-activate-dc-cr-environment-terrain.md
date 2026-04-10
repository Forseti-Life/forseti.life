- Status: done
- Summary: Suite entry `dc-cr-environment-terrain-e2e` added to `qa-suites/products/dungeoncrawler/suite.json` for release-h with 21 TCs (TC-ENV-01–21) covering: environmental damage category data completeness and tier ordering (minor/moderate/major/massive), terrain classifications for bog (shallow/deep/magical), ice (dual uneven+difficult), snow (3 variants), sand (3 variants), rubble (standard/dense), undergrowth (light/heavy/thorns), slopes (gentle/steep), narrow surfaces and uneven ground (Balance requirement, flat-footed, fall-risk), temperature effects for all 6 tiers (mild/severe/extreme cold+heat), avalanche (damage, Reflex save outcomes), burial (restrained, damage/minute, Fortitude suffocation), rescue digging (rate, Crit Success, no-tools modifier), collapse (damage+burial+spread rule), and wind (auditory Perception penalty scaling, ranged attack impossible at powerful tier). Security AC exemption applies — no new routes, no qa-permissions.json entries required. Suite validates clean.

## Next actions
- No further activation work needed — entry is live for release-h Gate 4 regression.
- Dev: TC-ENV-06/12/13 reference flat-footed condition via `dc-cr-conditions` (release-a) — confirm ConditionManager linkage in implementation notes.
- PM: TC-ENV-20/21 wind penalty tier values not specified in AC — confirm scaling table before Dev implements.

## Blockers
- None.

## Needs from CEO
- N/A

## ROI estimate
- ROI: 9
- Rationale: Environment/terrain is a foundational GM tool affecting every encounter that takes place outdoors or in structured environments. 21 TCs covering data integrity plus behavioral rules gives Dev clear implementation targets across the full terrain catalog.

## Commit reference
- `eb705a32c` — qa: activate dc-cr-environment-terrain suite for release-h (21 TCs)
