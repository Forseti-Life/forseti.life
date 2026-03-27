# Outbox: dc-cr-ancestry-system implementation

- Status: done
- Summary: Implemented the dc-cr-ancestry-system feature for dungeoncrawler. The ancestry content type and 6 core seed nodes (Human, Elf, Dwarf, Gnome, Goblin, Halfling) already existed; this ticket added the discrete Drupal field storage layer (update hook 10030: 7 fields — hp, size, speed, boosts, flaws, languages, senses — created and populated from CharacterManager::ANCESTRIES), ancestry ID validation in CharacterApiController (invalid ID → 400), full step 2 ancestry processing in CharacterCreationStepController (applies boosts/flaws/hp/speed, handles re-selection reversal, validates free-boost duplicates and boost/flaw conflicts), and AncestrySystemTest.php with all 19 PHPUnit test cases (TC-AN-01..19). All routes verified live at localhost:8080.

## Next actions
- QA handoff: run `./vendor/bin/phpunit -c phpunit.xml.dist --testsuite=functional` (AncestrySystemTest 19 cases) and `scripts/site-audit-run.sh dungeoncrawler` (role-url-audit: `GET /ancestries` and `GET /ancestries/{id}` must return 200 for anon)
- QA to add `qa-permissions.json` entries for `/ancestries` and `/ancestries/{id}` (anon → 200) per TC-AN-15

## Blockers
- None

## Stage-0 confirmations (all 4 from test plan)

1. **Route paths**: `GET /ancestries` and `GET /ancestries/{id}` registered with `_access: TRUE` (public). URL structure: `/ancestries`, `/ancestries/{id}` where `id` is lowercase slug (e.g. `dwarf`, `half-elf`). ✅
2. **Storage format**: Ancestry attributes stored as multi-value string fields on the `ancestry` content type (`field_ancestry_boosts`, `field_ancestry_flaws`, `field_ancestry_languages`, `field_ancestry_senses`) + single-value ints/strings (`field_ancestry_hp`, `field_ancestry_speed`, `field_ancestry_size`). Human boosts = `['Free','Free']`; Human flaw = empty. Matches AC spec ✅
3. **Boost/flaw mechanics**: Human free-boost validation in `validateStepRequirements` rejects duplicate `ancestry_boosts` selections (422) and boost/flaw conflicts (422). Re-selection reversal uses `_prev_ancestry` + `_prev_ancestry_free_boosts` stored in character_data. ✅
4. **Human free-boost API shape**: `GET /ancestries/human` returns `{"boosts":["Free","Free"],"flaw":null}`. Client submits `ancestry_boosts: ["Strength","Dexterity"]` (2 distinct ability names). Contract confirmed ✅

## Commits
- `79a6d3dfe` — feat(dc-cr-ancestry-system): add discrete fields, ancestry validation, ability stat application, AncestrySystemTest
- `(docs)` — implementation notes updated with testing evidence and stage-0 confirmations (committed below)

## Rollback steps
1. `git revert 79a6d3dfe --no-edit` — reverts all code changes
2. `drush cr` — clear caches
3. No destructive schema change: update hook 10030 adds fields to ancestry nodes only; existing character data (ancestry stored as string in `dc_campaign_characters.ancestry`) is unaffected.
4. If field removal needed: `drush field:delete --field=field_ancestry_hp` (repeat for each field).

## ROI estimate
- ROI: 18
- Rationale: Ancestry system is a P0 prerequisite for character creation; without it, players cannot build a character. Completing this unblocks character creation, background, class, and downstream feat tree features.
