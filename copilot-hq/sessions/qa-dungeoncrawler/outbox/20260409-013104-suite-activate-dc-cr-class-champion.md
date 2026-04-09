# Suite Activation: dc-cr-class-champion

- Feature: dc-cr-class-champion
- Inbox: 20260409-013104-suite-activate-dc-cr-class-champion
- Date: 2026-04-09
- Release: 20260409-dungeoncrawler-release-c

## Result: ACTIVATED

Stamped the existing `dc-cr-class-champion-e2e` entry in `qa-suites/products/dungeoncrawler/suite.json` with `activated_release: 20260409-dungeoncrawler-release-c`. Entry had 41 test cases with no prior activation stamp.

### Suite entry
- ID: `dc-cr-class-champion-e2e`
- Type: e2e (PHPUnit functional + role-url-audit)
- feature_id: `dc-cr-class-champion` ✓ tagged
- TCs: 41 (TC-CHP-01–41), all status=active
- required_for_release: true ✓
- activated_release: `20260409-dungeoncrawler-release-c` ✓

### Permission rules
- No new routes introduced by this feature — ACL exemption confirmed (command.md: "security exemption — no new routes beyond existing character creation and leveling forms").
- No new entries added to `org-chart/sites/dungeoncrawler/qa-permissions.json`.

### Suite validation
`python3 scripts/qa-suite-validate.py` — dungeoncrawler suite: **OK** (60 suites, all have artifacts).
Note: pre-existing validation ERROR in `qa-suites/products/forseti/suite.json` (suite `forseti-csrf-seed-consistency` missing artifacts) — unrelated to this change; not introduced by this commit.

### Coverage summary (41 TCs active)
- TC-CHP-01–03: Class fundamentals (selection, STR/DEX key ability, HP calc, initial proficiencies)
- TC-CHP-04–09: Deity/cause/code (Paladin LG, Redeemer NG, Liberator CG, invalid combos, Deific Weapon, tenet hierarchy, code violation + atone)
- TC-CHP-10–12: Champion's Reaction (Retributive Strike, Glimpse of Redemption A/B, Liberating Step)
- TC-CHP-13–15: Focus pool (initial state + feat growth, Lay on Hands, devotion spell auto-heighten + CHA)
- TC-CHP-16+: Level-gated features, divine ally, feat progression, edge cases
