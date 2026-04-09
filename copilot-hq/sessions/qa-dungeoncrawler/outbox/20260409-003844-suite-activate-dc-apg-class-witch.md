# Suite Activation: dc-apg-class-witch

- Feature: dc-apg-class-witch
- Inbox: 20260409-003844-suite-activate-dc-apg-class-witch
- Date: 2026-04-09
- Release: 20260409-dungeoncrawler-release-c

## Result: ACTIVATED (re-stamped for current release)

The suite entry `dc-apg-class-witch-e2e` was already present in `qa-suites/products/dungeoncrawler/suite.json` with all 18 active test cases. Updated `activated_release` to `20260409-dungeoncrawler-release-c` (release-b closed 01:01 UTC; release-c started 01:05 UTC).

### Suite entry
- ID: `dc-apg-class-witch-e2e`
- Type: e2e (playwright/character-creation + encounter + downtime)
- feature_id: `dc-apg-class-witch` ✓ tagged
- TCs: 18 (TC-WCH-01–18), all status=active
- required_for_release: true ✓
- activated_release: `20260409-dungeoncrawler-release-c` ✓

### Permission rules
- No new routes introduced by this feature — ACL exemption confirmed (command.md states "Security AC exemption granted: no new routes").
- No new entries added to `org-chart/sites/dungeoncrawler/qa-permissions.json`.

### Suite validation
`python3 scripts/qa-suite-validate.py` → **OK: validated 5 suite manifest(s)**

### Coverage summary (18 TCs active)
- TC-WCH-01–02: Class record/saves, patron theme selection (7 themes, locks at L1)
- TC-WCH-03–06: Familiar (mandatory, bonus abilities L1/6/12/18, death/spell preservation, prepared spellcasting via commune)
- TC-WCH-07–08: Spell repertoire (starting count 16, +2/level-up, scroll absorption, familiar-to-familiar transfer)
- TC-WCH-09–12: Hexes (FP cost, one-per-turn, hex cantrips auto-heighten, separate from prepared slots, Refocus via commune)
- TC-WCH-13–15: Witch Lessons (tiered mechanism, Basic L2/Greater L6/Major L10, hex+spell pairs)
- TC-WCH-16: Notable hexes (Cackle extension, Evil Eye status, Phase Familiar)
- TC-WCH-17–18: Integration (familiar spell count growth) + Edge (Cackle on hex cantrip is valid)

No gaps identified.
