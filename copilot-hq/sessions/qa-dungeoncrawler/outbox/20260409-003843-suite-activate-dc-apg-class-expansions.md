# Suite Activation: dc-apg-class-expansions

- Feature: dc-apg-class-expansions
- Inbox: 20260409-003843-suite-activate-dc-apg-class-expansions
- Date: 2026-04-09
- Release: 20260409-dungeoncrawler-release-b

## Result: ACTIVATED (re-stamped for current release)

The suite entry `dc-apg-class-expansions-e2e` was already present in `qa-suites/products/dungeoncrawler/suite.json` with all 16 active test cases. Updated `activated_release` and `activated_date` to reflect the current release cycle.

### Suite entry
- ID: `dc-apg-class-expansions-e2e`
- Type: e2e (playwright/character-creation + encounter + downtime)
- feature_id: `dc-apg-class-expansions` ✓ tagged
- TCs: 16 (TC-EXP-01–16), all status=active
- required_for_release: true ✓
- activated_release: `20260409-dungeoncrawler-release-b` ✓

### Permission rules
- No new routes introduced by this feature — ACL exemption confirmed (command.md states "Security AC exemption granted: no new routes").
- No new entries added to `org-chart/sites/dungeoncrawler/qa-permissions.json`.

### Suite validation
`python3 scripts/qa-suite-validate.py` → **OK: validated 5 suite manifest(s)**

### Coverage summary (16 TCs active)
- TC-EXP-01–04: Alchemist Toxicologist (field availability, 1-action poison, class DC sub, L5/L15 discoveries)
- TC-EXP-05: Barbarian Superstition instinct (anathema scope and exclusions)
- TC-EXP-06–07: Bard Warrior muse (Martial Performance grant, fear repertoire, Song of Strength)
- TC-EXP-08: Champion evil alignment options (Uncommon gate)
- TC-EXP-09–10: Rogue Eldritch Trickster and Mastermind rackets
- TC-EXP-11–12: Sorcerer Genie/Nymph bloodlines
- TC-EXP-13–15: Wizard Staff Nexus thesis (makeshift staff, L8/L16 slot stacking, free cantrip)
- TC-EXP-16: Integration (all 6 expansions visible in correct class screens)

No gaps identified.
