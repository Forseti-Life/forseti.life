# Suite Activation: dc-b1-bestiary1

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-09T17:53:46+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-b1-bestiary1"`**  
   This links the test to the living requirements doc at `features/dc-b1-bestiary1/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-b1-bestiary1-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-b1-bestiary1",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-b1-bestiary1"`**  
   Example:
   ```json
   {
     "id": "dc-b1-bestiary1-<route-slug>",
     "feature_id": "dc-b1-bestiary1",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-b1-bestiary1",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-b1-bestiary1

## Coverage summary
- AC items: 13 (8 happy path, 2 edge cases, 2 failure modes + security)
- Test cases: 10 (TC-BST-01–10)
- Suites: unit (content type schema), playwright (encounter filtering, access control)
- Security: CSRF on mutation routes; GM-only access required

---

## TC-BST-01 — Creature content type exists with required fields
- Description: `creature` content type has all required stat block fields
- Suite: unit/content-type
- Expected: creature node schema includes level, rarity, traits, perception, languages, skills, senses, ac, saves, hp, immunities, weaknesses, resistances, speeds, attacks, abilities fields
- AC: Creature Content Type-1, Creature Content Type-2

## TC-BST-02 — Attack array encodes required fields
- Description: Each attack record has name, traits, damage_dice, damage_type, reach
- Suite: unit/content-type
- Expected: sample creature attack = {name: "Claw", traits: [agile], damage_dice: "1d6", damage_type: "slashing", reach: 5}
- AC: Creature Content Type-3

## TC-BST-03 — Ability array encodes required fields
- Description: Each ability record has name, action_cost, trigger, frequency, traits, save_dc, effect
- Suite: unit/content-type
- Expected: sample ability validates against schema with all required fields; trigger and frequency nullable
- AC: Creature Content Type-4

## TC-BST-04 — Encounter filter by level range
- Description: Encounter tooling filters creatures by level range (inclusive)
- Suite: playwright/encounter
- Expected: filter(level_min=1, level_max=3) returns creatures at levels 1, 2, and 3; level 0 and 4 excluded
- AC: Encounter Filtering-1, Failure Modes-1

## TC-BST-05 — Encounter filter by trait
- Description: Encounter tooling filters creatures by trait tag
- Suite: playwright/encounter
- Expected: filter(trait="undead") returns only creatures with undead trait
- AC: Encounter Filtering-1

## TC-BST-06 — Encounter filter by tactical role
- Description: Encounter tooling filters creatures by tactical role enum
- Suite: playwright/encounter
- Expected: filter(role="brute") returns only creatures with tactical_role = brute
- AC: Encounter Filtering-2

## TC-BST-07 — Player receives 403 on mutation route
- Description: Player-role user cannot access creature mutation routes
- Suite: playwright/access-control
- Expected: POST /admin/dungeoncrawler/creature returns 403 when authenticated as player role
- AC: GM vs. Player Access-2, Failure Modes-2

## TC-BST-08 — GM can access mutation route with _campaign_gm_access
- Description: GM-role user can access creature mutation routes
- Suite: playwright/access-control
- Expected: POST /admin/dungeoncrawler/creature returns 200/201 when authenticated as GM with _campaign_gm_access: TRUE
- AC: GM vs. Player Access-2

## TC-BST-09 — CSRF required on mutation routes
- Description: POST/PATCH to creature routes without CSRF header returns 403
- Suite: playwright/security
- Expected: POST /admin/dungeoncrawler/creature without _csrf_request_header_mode token returns 403
- AC: Security — CSRF

## TC-BST-10 — Multi-attack and multi-ability arrays support arbitrary length
- Description: A creature can have multiple attacks and abilities without schema error
- Suite: unit/content-type
- Expected: saving a creature with 5 attacks and 7 abilities succeeds; all records preserved
- AC: Edge Case-1

### Acceptance criteria (reference)

# Acceptance Criteria: dc-b1-bestiary1

## Gap analysis reference
- DB sections: b1/s01–s03 (Baseline Requirements + Integration Notes, 18 REQs)
- Depends on: dc-cr-encounter-rules ✓, dc-cr-npc-system ✓

---

## Happy Path

### Creature Content Type
- [ ] `[NEW]` A `creature` content type exists in `dungeoncrawler_content` with all required stat block fields.
- [ ] `[NEW]` Required fields: `level` (int), `rarity` (enum: common/uncommon/rare/unique), `traits[]` (term refs), `perception` (int), `languages[]` (term refs), `skills{}` (skill name → modifier map), `senses[]` (type + range), `ac` (int), `saves{fort, ref, will}` (ints), `hp` (int), `immunities[]`, `weaknesses[]` (name + value), `resistances[]` (name + value), `speeds{}` (land/fly/swim/burrow), `attacks[]`, `abilities[]`.
- [ ] `[NEW]` Each `attacks[]` entry encodes: `name`, `traits[]`, `damage_dice`, `damage_type`, `reach` (ft).
- [ ] `[NEW]` Each `abilities[]` entry encodes: `name`, `action_cost` (enum: free/reaction/1/2/3), `trigger`, `frequency`, `traits[]`, `save_dc` (int or null), `effect` (text).

### Encounter Filtering
- [ ] `[NEW]` Encounter tooling provides a filtering interface to select creatures by: level range, trait, and tactical role (brute/skirmisher/controller/support/spellcaster).
- [ ] `[NEW]` Tactical role is stored on each creature as an enum field.

### Data Import
- [ ] `[NEW]` An import pipeline loads creature data from the Bestiary 1 source file via `drush` import once the stat block schema is confirmed.
- [ ] `[NEW]` Import pipeline sanitizes all text fields against free-text rule injection.

### GM vs. Player Access
- [ ] `[NEW]` Creature library is read-only for players.
- [ ] `[NEW]` GM-only routes for creature mutation (import, override) require `_campaign_gm_access: TRUE`.

---

## Edge Cases
- [ ] `[NEW]` A creature may have multiple attacks and multiple abilities — the arrays must accept arbitrary length (no hardcoded max).
- [ ] `[NEW]` Speed zero values (e.g., no fly speed) are omitted from the speeds map, not stored as 0.

## Failure Modes
- [ ] `[TEST-ONLY]` Encounter filter by level range must be inclusive on both ends (e.g., levels 1–3 includes level 1, 2, and 3).
- [ ] `[TEST-ONLY]` Player-role users must receive 403 on any creature mutation route.

## Security acceptance criteria
- Authentication: player read-only; GM-only mutation routes require `_campaign_gm_access: TRUE`
- CSRF: all POST/PATCH creature import or override routes require `_csrf_request_header_mode: TRUE`
- Input validation: stat block fields validated against defined types and ranges; no free-text rule injection; import pipeline sanitizes all text fields
- PII/logging: no PII logged; creature id + encounter id + action type only in logs
