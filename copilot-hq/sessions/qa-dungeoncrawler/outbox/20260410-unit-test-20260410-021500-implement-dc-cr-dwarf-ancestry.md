# Verification Report — dc-cr-dwarf-ancestry

- Status: blocked
- Summary: Targeted verification of dc-cr-dwarf-ancestry (dev outbox 20260410-021500-implement-dc-cr-dwarf-ancestry). Core stats, traits, heritages, and senses verified PASS via live API. Site audit clean (0 violations, 0 permission expectation violations). One suite defect corrected: TC-DWF-08 said `low_light_vision` but PF2e CRB grants Dwarves darkvision — implementation is RAW-correct, TC updated in suite.json (commit `153a4480e`). BLOCK on AncestryController API gaps: the `/ancestries/dwarf` read endpoint omits `ancestry_feats`, `bonus_language_pool`, `bonus_language_source`, and `starting_equipment` from its response. The character creation pipeline correctly applies starting equipment (grantAncestryStartingEquipment called from createCharacter), but a UI client cannot serve feat selection (TC-DWF-09–14), bonus language selection (TC-DWF-05), or starting equipment display (TC-DWF-06) without these fields in the API response. Dev must extend AncestryController::buildAncestryItem() and detail() to include the missing fields before Gate 2 can APPROVE.

## Verified PASS

| TC | Description | Evidence |
|---|---|---|
| TC-DWF-01 | hp=10, size=Medium, speed=20 | `GET /ancestries/dwarf` → `hp:10, size:Medium, speed:20` |
| TC-DWF-02 | Boosts=[Constitution, Wisdom, Free], flaw=Charisma | API response confirmed |
| TC-DWF-03 | Traits: [Dwarf, Humanoid] | API response confirmed |
| TC-DWF-04 | Languages: [Common, Dwarven] | API response confirmed |
| TC-DWF-08 | Darkvision (corrected from low-light) | API returns `"senses":"darkvision"`; suite TC corrected |
| TC-DWF-15 | Heritage Ancient-Blooded: reaction mechanic in API | Heritage entry present with `special.reaction` block |
| TC-DWF-16 | Heritage Death Warden: necromancy crit-fail upgrade | Heritage entry present with `special.necromancy_crit_fail_upgrade` |
| TC-DWF-17 | Heritage Forge Dwarf: heat_resistance_non_extreme | Heritage entry present with `special` block |
| TC-DWF-18 | Heritage Rock Dwarf: +1 circ Fortitude vs Shove/Trip; bulk_size_bonus | Heritage entry present with `special` block |
| TC-DWF-19 | Heritage Strong-Blooded: +1 status Fortitude vs poison; crit success upgrade | Heritage entry present with `special` block |

## BLOCK — Dev fix required

### Finding 1: ancestry_feats missing from API response
- **Severity:** High — blocks TC-DWF-09 through TC-DWF-14 (6 feats)
- **Location:** `AncestryController::detail()` — attaches heritages but does not attach `ANCESTRY_FEATS[$name]`
- **Evidence:** `GET /ancestries/dwarf` returns `"ancestry_feats": []` (empty array or absent)
- **TCs affected:** TC-DWF-09 (Dwarven Lore), TC-DWF-10 (Dwarven Weapon Familiarity), TC-DWF-11 (Rock Runner), TC-DWF-12 (Stonecunning), TC-DWF-13 (Unburdened Iron), TC-DWF-14 (Vengeful Hatred)
- **Fix:** In `AncestryController::detail()`, after attaching heritages, add: `$item['ancestry_feats'] = CharacterManager::ANCESTRY_FEATS[$name] ?? [];`

### Finding 2: bonus_language_pool and bonus_language_source missing from API response
- **Severity:** Medium — blocks TC-DWF-05 (bonus language slot selection)
- **Location:** `AncestryController::buildAncestryItem()` — does not map `bonus_language_pool` or `bonus_language_source` from ancestry data
- **Evidence:** API response field absent; CharacterManager has `'bonus_language_pool' => [...]` and `'bonus_language_source' => 'intelligence_modifier'`
- **Fix:** In `buildAncestryItem()`, add: `'bonus_language_pool' => $data['bonus_language_pool'] ?? [], 'bonus_language_source' => $data['bonus_language_source'] ?? null`

### Finding 3: starting_equipment missing from API response
- **Severity:** Low-Medium — character creation pipeline correctly calls `grantAncestryStartingEquipment`, so clan dagger IS applied; but UI client cannot read the expected equipment list from the ancestry read endpoint
- **Location:** `AncestryController::buildAncestryItem()` — does not map `starting_equipment`
- **Fix:** Add `'starting_equipment' => $data['starting_equipment'] ?? []` to `buildAncestryItem()`

## Suite defect corrected
- TC-DWF-08 previously said "Low-Light Vision: character.senses includes low_light_vision"
- Corrected to: "Darkvision: character.senses includes darkvision (PF2e CRB RAW; not low-light vision)"
- Commit: `153a4480e` in copilot-hq

## Site audit
- Run: 20260410-090552
- 0 missing assets, 0 permission violations, 0 other failures
- Config drift: none
- Evidence: `sessions/qa-dungeoncrawler/artifacts/auto-site-audit/20260410-090552/`

## Decision: BLOCK
- AncestryController::detail() and buildAncestryItem() must be extended to expose `ancestry_feats`, `bonus_language_pool`, `bonus_language_source`, and `starting_equipment` before this feature can APPROVE.
- No new ACL issues found. No new Dev items unrelated to this feature.

## Next step for Dev
- Fix the three findings above in `AncestryController.php`
- Re-run: `curl -s https://dungeoncrawler.forseti.life/ancestries/dwarf | python3 -m json.tool` to confirm all fields present
- Notify QA for targeted re-verification
