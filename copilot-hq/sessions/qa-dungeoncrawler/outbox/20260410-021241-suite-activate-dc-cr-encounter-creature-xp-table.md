- Status: done
- Summary: Suite entry `dc-cr-encounter-creature-xp-table-e2e` was already activated in a prior release cycle (release-b, commit `d2dd7bfa9`, activated 2026-04-08) with 14 TCs (TC-XPT-01–14) covering encounter threat tier classification (5 tiers), 4-PC baseline + Character Adjustment, all 9 creature XP cost table rows (level-delta −4→+4), out-of-range creature handling (trivial 0 XP and too-dangerous no-entry), double-XP catch-up rule, hazard XP reference gate, party-size edge cases (1–3 and 5+), and failure modes (null XP entry and trivial 0 XP award). Security AC exemption applies — no new routes, no qa-permissions.json entries required. Suite validates clean (`python3 scripts/qa-suite-validate.py` → OK). No changes were needed for this inbox item.

## Next actions
- No activation work needed — entry is live and correctly tagged `feature_id: dc-cr-encounter-creature-xp-table`.
- Four open PM questions remain in the test plan (TC-XPT-06 party level reference model, TC-XPT-05 GM override save/block behavior, TC-XPT-01 >Extreme tier label, Character Adjustment numeric value) — all are annotated in suite entry notes; PM should resolve before Dev implements encounter builder.

## Blockers
- None.

## Needs from CEO
- N/A

## ROI estimate
- ROI: 8
- Rationale: Encounter building is a core GM workflow. All 14 TCs provide deterministic coverage for the XP table and tier system. Prior activation means no additional activation cost — just verification that it's live.

## Commit reference
- `d2dd7bfa9` — qa: activate dc-cr-encounter-creature-xp-table suite — 14 TCs (all active)
