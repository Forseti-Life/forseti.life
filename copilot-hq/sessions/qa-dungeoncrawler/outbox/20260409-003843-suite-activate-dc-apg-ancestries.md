# Suite Activation: dc-apg-ancestries

- Feature: dc-apg-ancestries
- Inbox: 20260409-003843-suite-activate-dc-apg-ancestries
- Date: 2026-04-09
- Release: 20260409-dungeoncrawler-release-b

## Result: ACTIVATED (re-stamped for current release)

The suite entry `dc-apg-ancestries-e2e` was already present in `qa-suites/products/dungeoncrawler/suite.json` with all 24 active test cases from Release-B. Updated `activated_release` and `activated_date` to reflect the current release cycle.

### Suite entry
- ID: `dc-apg-ancestries-e2e`
- Type: e2e (playwright/character-creation)
- feature_id: `dc-apg-ancestries` ✓ tagged
- TCs: 24 (TC-APGA-01–24), all status=active
- required_for_release: true ✓
- activated_release: `20260409-dungeoncrawler-release-b` ✓

### Permission rules
- No new routes introduced by this feature — ACL exemption confirmed (command.md states "Security AC exemption granted: no new routes").
- No new entries added to `org-chart/sites/dungeoncrawler/qa-permissions.json`.

### Suite validation
`python3 scripts/qa-suite-validate.py` → **OK: validated 5 suite manifest(s)**

### Coverage summary (24 TCs active)
- TC-APGA-01–02: Catfolk (core stats + heritages)
- TC-APGA-03–04: Kobold (core stats + Draconic Exemplar + heritages)
- TC-APGA-05: Orc (core stats + Grave Orc negative healing)
- TC-APGA-06: Ratfolk (core stats + heritages)
- TC-APGA-07–08: Tengu (core stats + Sharp Beak + heritages)
- TC-APGA-09–10: Versatile heritage rules (slot replacement, feat pool, Uncommon gate)
- TC-APGA-11–14: VH implementations (Aasimar, Changeling, Dhampir, Duskwalker, Tiefling)
- TC-APGA-15: APG supplemental feats for CRB ancestries
- TC-APGA-16–19: APG backgrounds (format, Haunted, Fey-Touched, Returned)
- TC-APGA-20–22: Integration checks (ancestry/VH selectors, Draconic Exemplar UI)
- TC-APGA-23–24: Edge cases (negative healing consistency, Spellscale one-cantrip)

No gaps identified.
