# Suite Activation: dc-cr-gnome-ancestry

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-09T01:33:49+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-gnome-ancestry"`**  
   This links the test to the living requirements doc at `features/dc-cr-gnome-ancestry/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-gnome-ancestry-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-gnome-ancestry",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-gnome-ancestry"`**  
   Example:
   ```json
   {
     "id": "dc-cr-gnome-ancestry-<route-slug>",
     "feature_id": "dc-cr-gnome-ancestry",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-gnome-ancestry",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-gnome-ancestry

## Coverage summary
- AC items: 20 (14 happy path, 3 edge cases, 3 failure modes)
- Test cases: 14 (TC-GNM-01–14)
- Suites: playwright (character creation flows)
- Security: AC exemption granted (no new routes)

---

## TC-GNM-01 — Core stats: HP, size, speed
- Description: Gnome ancestry grants HP 8, Small size, Speed 25 ft
- Suite: playwright/character-creation
- Expected: character.hp_from_ancestry = 8; size = Small; speed = 25
- AC: Core Stats-1

## TC-GNM-02 — Ability boosts and flaw
- Description: Constitution and Charisma get fixed boosts; one free boost; Strength has a flaw
- Suite: playwright/character-creation
- Expected: con +2, cha +2, one player-chosen +2; str –2 applied automatically; free boost cannot be applied to con or cha
- AC: Core Stats-2, Edge Case-3

## TC-GNM-03 — Traits
- Description: Gnome and Humanoid traits applied
- Suite: playwright/character-creation
- Expected: character.traits includes [Gnome, Humanoid]
- AC: Core Stats-4

## TC-GNM-04 — Starting languages
- Description: Common, Gnomish, Sylvan granted automatically
- Suite: playwright/character-creation
- Expected: character.languages includes [Common, Gnomish, Sylvan]
- AC: Core Stats-5

## TC-GNM-05 — Bonus languages by Int modifier
- Description: Each point of positive Int modifier unlocks one bonus language from the restricted list
- Suite: playwright/character-creation
- Expected: bonus_language_slots = max(0, int_modifier); only listed languages selectable
- AC: Core Stats-6, Edge Case-3

## TC-GNM-06 — Low-Light Vision
- Description: Gnome has Low-Light Vision sense
- Suite: playwright/character-creation
- Expected: character.senses includes [low-light-vision]
- AC: Senses-1

## TC-GNM-07 — Heritage unlocked
- Description: Gnome ancestry unlocks exactly five heritages
- Suite: playwright/character-creation
- Expected: heritage_options = [chameleon, fey-touched, sensate, umbral, wellspring]; exactly one must be chosen
- AC: Heritage-1, Heritage-2

## TC-GNM-08 — HP differentiated from Dwarf
- Description: Gnome HP 8, not 10
- Suite: playwright/character-creation
- Expected: hp_from_ancestry = 8 when ancestry = gnome; does not inherit dwarf value
- AC: Failure Modes-1

## TC-GNM-09 — Speed differentiated from Dwarf
- Description: Gnome Speed 25, not 20
- Suite: playwright/character-creation
- Expected: speed = 25 when ancestry = gnome
- AC: Failure Modes-2

## TC-GNM-10 — Str flaw not overrideable
- Description: Strength flaw is applied automatically and cannot be skipped or assigned elsewhere
- Suite: playwright/character-creation
- Expected: str_from_flaw = -2; no UI control to remove it; only free boost cannot target str as a second boost
- AC: Failure Modes-3

## TC-GNM-11 — Small-size Bulk rules apply
- Description: Small size applies inventory Bulk rules for Small creatures
- Suite: playwright/character-creation
- Expected: character.size = Small; bulk_limit uses Small-creature rules
- AC: Edge Case-1

## TC-GNM-12 — First World Magic feat available
- Description: First World Magic (Gnome, 1st) appears in feat selection at character creation
- Suite: playwright/character-creation
- Expected: feat_pool[gnome][level_1] includes dc-cr-first-world-magic
- AC: Ancestry Feats

## TC-GNM-13 — Fey Fellowship feat available
- Description: Fey Fellowship (Gnome, 1st) appears in feat selection
- Suite: playwright/character-creation
- Expected: feat_pool[gnome][level_1] includes dc-cr-fey-fellowship
- AC: Ancestry Feats

## TC-GNM-14 — Gnome Weapon Familiarity: glaive and kukri trained
- Description: Selecting Gnome Weapon Familiarity grants trained proficiency in glaive and kukri
- Suite: playwright/character-creation
- Expected: character.weapon_proficiencies includes [glaive: trained, kukri: trained]
- AC: Ancestry Feats

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-gnome-ancestry

## Gap analysis reference
- DB sections: core/ch02 (Gnome Ancestry)
- Depends on: dc-cr-ancestry-system ✓, dc-cr-heritage-system ✓, dc-cr-languages ✓

---

## Happy Path

### Core Stats
- [ ] `[NEW]` Gnome ancestry: HP 8, Small size, Speed 25 feet.
- [ ] `[NEW]` Ability boosts: Constitution, Charisma, and one free boost.
- [ ] `[NEW]` Ability flaw: Strength.
- [ ] `[NEW]` Traits: Gnome, Humanoid.
- [ ] `[NEW]` Starting languages: Common, Gnomish, Sylvan.
- [ ] `[NEW]` Bonus languages: Draconic, Dwarven, Elven, Goblin, Jotun, Orcish, or one uncommon language — one per point of positive Intelligence modifier.

### Senses
- [ ] `[NEW]` Gnomes have Low-Light Vision (see in dim light as bright light; see twice as far from light sources in darkness).

### Heritage Selection (mandatory at character creation)
- [ ] `[NEW]` Selecting Gnome ancestry unlocks exactly five heritages: Chameleon, Fey-touched, Sensate, Umbral, and Wellspring.
- [ ] `[NEW]` Exactly one heritage must be chosen before character creation can be completed.

### Ancestry Feats (1st-level Gnome feats available at character creation)
- [ ] `[NEW]` Animal Accomplice (1st): a familiar from a limited list (bat, cat, gecko, etc.) using the familiar rules.
- [ ] `[NEW]` Burrow Elocutionist (1st): speak with and understand burrowing animals as if using speak with animals.
- [ ] `[NEW]` Fey Fellowship (1st): see dc-cr-fey-fellowship acceptance criteria.
- [ ] `[NEW]` First World Magic (1st): see dc-cr-first-world-magic acceptance criteria.
- [ ] `[NEW]` Gnome Obsession (1st): trained in one Lore subcategory; +1 circumstance bonus to rolls for that obsession during downtime.
- [ ] `[NEW]` Gnome Weapon Familiarity (1st): trained in glaive and kukri; gnome-trait weapons treated as martial if already in martial group.
- [ ] `[NEW]` Illusion Sense (1st): +1 circumstance bonus to Will saves vs. illusions; passive Perception to disbelieve illusions rolled automatically when entering their area.
- [ ] `[NEW]` Natural Performer (1st): trained in Performance; gain one Performance-related power (singing, dancing, or acting — chosen at selection).
- [ ] `[NEW]` Vibrant Display (1st): use 2 actions to display vivid coloration; creatures within 10 ft must succeed a Will save or become fascinated until end of next turn.

---

## Edge Cases
- [ ] `[NEW]` Gnome size is Small — inventory Bulk limits and some combat effects apply Small-size rules.
- [ ] `[NEW]` Con + Cha boosts applied before free boost; free boost may NOT be applied to Con or Cha a second time.
- [ ] `[NEW]` Bonus languages slot count = max(0, int_modifier); list is restricted to the six listed options plus one DM-approved uncommon language.

## Failure Modes
- [ ] `[TEST-ONLY]` HP 8 (not 10): must not inherit Dwarf's HP value.
- [ ] `[TEST-ONLY]` Speed 25 ft (not 20): must not inherit Dwarf's Speed penalty.
- [ ] `[TEST-ONLY]` Str flaw applied automatically: must not be overrideable by player at character creation.

## Security acceptance criteria
- Security AC exemption: game-mechanic ancestry selection; no new routes beyond existing character creation flow
