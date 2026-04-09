# Suite Activation: dc-apg-archetypes

- Feature: dc-apg-archetypes
- Inbox: 20260409-003844-suite-activate-dc-apg-archetypes
- Date: 2026-04-09
- Release: 20260409-dungeoncrawler-release-c

## Result: ACTIVATED (re-stamped for current release)

The suite entry `dc-apg-archetypes-e2e` was already present in `qa-suites/products/dungeoncrawler/suite.json` with all 20 active test cases. Updated `activated_release` to `20260409-dungeoncrawler-release-c` (release-b closed at 01:01 UTC; release-c started at 01:05 UTC, before this inbox item was processed).

### Suite entry
- ID: `dc-apg-archetypes-e2e`
- Type: e2e (playwright/character-creation + encounter)
- feature_id: `dc-apg-archetypes` ✓ tagged
- TCs: 20 (TC-ARC-01–20), all status=active
- required_for_release: true ✓
- activated_release: `20260409-dungeoncrawler-release-c` ✓

### Permission rules
- No new routes introduced by this feature — ACL exemption confirmed (command.md states "Security AC exemption granted: no new routes").
- No new entries added to `org-chart/sites/dungeoncrawler/qa-permissions.json`.

### Suite validation
`python3 scripts/qa-suite-validate.py` → **OK: validated 5 suite manifest(s)**

### Coverage summary (20 TCs active)
- TC-ARC-01–04: Archetype system rules (L2 gate, 2-before-dedication, prerequisite enforcement, proficiency cap)
- TC-ARC-05–07: Combat archetypes (Acrobat proficiency scaling, Assassin Mark for Death, Marshal aura)
- TC-ARC-08: Skill archetype (Bounty Hunter + Hunt Prey)
- TC-ARC-09–12: Magic archetypes (Beastmaster, Blessed One, Familiar Master, Shadowdancer)
- TC-ARC-13–14: Social/skill archetypes (Vigilante dual identity, Snarecrafter)
- TC-ARC-15: Multiclass spellcasting progression
- TC-ARC-16–17: Integration (26+ dedication feats at L2, 2-before-dedication UI enforcement)
- TC-ARC-18–20: Edge cases (Archer proficiency scaling, Cavalier mount dependency, Ritualist without class spellcasting)

No gaps identified.

### Note on release transition
Release-b closed (01:01 UTC) and release-c started (01:05 UTC) between the dispatch of this item and its processing. Release-b scope included dc-apg-archetypes as implemented; this activation carries forward into release-c.
