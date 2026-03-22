# Implementation: dc-cr-character-leveling — Done

- Status: done
- Summary: Implemented full PF2e character leveling and advancement system for dc-cr-character-leveling. Created CharacterLevelingService (milestone-gated level-up, auto-apply class features, ability boosts, skill increases, feat selection, idempotency, admin force/reset, milestone GM control) and CharacterLevelingController (9 REST endpoints). Added CLASS_ADVANCEMENT constant for 7 classes (levels 1–20) and getClassAdvancement() static helper to CharacterManager. Registered service, 8 new routes, and re-pointed the existing stub level-up route to the real implementation. Cache rebuild clean; all 9 routes verified registered; service instantiates. Forseti.life commit: `a5b8f3d98`.

## What changed

### New files
- `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/CharacterLevelingService.php` — full leveling logic
- `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Controller/CharacterLevelingController.php` — 9 REST endpoints

### Modified files
- `src/Service/CharacterManager.php` — added `CLASS_ADVANCEMENT` constant + `getClassAdvancement()` (+268 lines)
- `dungeoncrawler_content.services.yml` — registered `dungeoncrawler_content.character_leveling`
- `dungeoncrawler_content.routing.yml` — 8 new routes + updated existing level-up route

### HQ artifacts
- `features/dc-cr-character-leveling/02-implementation-notes.md` — full implementation notes (new routes, data shape, AC alignment, deviations, verification commands)

## New routes introduced (QA action required)

| Route | Method | Permission | administrator | dc_playwright_admin |
|---|---|---|---|---|
| `/api/character/{id}/level-up` | POST | `_character_access` | allow | allow |
| `/api/character/{id}/level-up/status` | GET | `_character_access` | allow | allow |
| `/api/character/{id}/level-up/ability-boosts` | POST | `_character_access` | allow | allow |
| `/api/character/{id}/level-up/skill-increase` | POST | `_character_access` | allow | allow |
| `/api/character/{id}/level-up/feat` | POST | `_character_access` | allow | allow |
| `/api/character/{id}/level-up/feats` | GET | `_character_access` | allow | allow |
| `/api/character/{id}/level-up/admin-force` | POST | `administer dungeoncrawler content` | allow | allow |
| `/api/character/{id}/level-up/admin-reset` | POST | `administer dungeoncrawler content` | allow | allow |
| `/api/character/{id}/milestone` | POST | `administer dungeoncrawler content` | allow | allow |

**`qa-dungeoncrawler`**: please add `dungeoncrawler-character-levelup` rule to `qa-permissions.json` before the next audit run.

## Verification

```bash
# Cache rebuild (confirmed clean)
cd /home/keithaumiller/forseti.life/sites/dungeoncrawler && vendor/bin/drush cr
# → [success] Cache rebuild complete.

# Service OK
vendor/bin/drush php:eval "\$s = \Drupal::service('dungeoncrawler_content.character_leveling'); print get_class(\$s);"
# → Drupal\dungeoncrawler_content\Service\CharacterLevelingService

# All 9 routes registered (confirmed via drush php:eval)
```

## Deviations / open items for QA

- **TC-015 (concurrency)**: `inProgress` flag prevents same-client double-trigger; full DB-level concurrency serialization is out of scope for this AC cycle. QA test plan flagged this as PHPUnit-level test.
- **TC-016 (missing class data)**: falls back to universal advancement (no exception thrown); unknown classes return class_feat slot + universal slots.
- **TC-019 (admin endpoints)**: force = `/level-up/admin-force`, reset = `/level-up/admin-reset`, both require `administer dungeoncrawler content`.
- **TC-006 (feat catalog)**: `GET /api/character/{id}/level-up/feats?slot_type=class_feat` is the prerequisite-filtered endpoint.
- **Skill increase fixture**: Fighter/Wizard/Rogue at levels 3, 7, 11, 15, 19 reliably have a skill increase slot.

## Rollback

```bash
git revert a5b8f3d98
cd /home/keithaumiller/forseti.life/sites/dungeoncrawler && vendor/bin/drush cr
```

No schema migration; data stored in existing `character_data` JSON column.

## Next actions
- `qa-dungeoncrawler`: add `dungeoncrawler-character-levelup` rule to `qa-permissions.json` (9 routes listed above) before audit run
- `qa-dungeoncrawler`: activate `dc-cr-character-leveling-e2e` suite per Stage 0 checklist in test plan
- `dev-dungeoncrawler`: process next inbox items (`dc-cr-dwarf-heritage-ancient-blooded`, `dc-cr-clan-dagger`)
