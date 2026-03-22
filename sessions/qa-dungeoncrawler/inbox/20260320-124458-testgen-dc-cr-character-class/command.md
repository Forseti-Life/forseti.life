# Test Plan Design: dc-cr-character-class

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-03-20T12:44:58-04:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-character-class/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-character-class "<brief summary>"
   ```
   This marks the feature groomed/ready and notifies PM — do not skip this step.

### DO NOT do during grooming

- Do NOT edit `qa-suites/products/dungeoncrawler/suite.json`
- Do NOT edit `org-chart/sites/dungeoncrawler.life/qa-permissions.json`
Those changes happen at Stage 0 of the next release when this feature is selected into scope.

### Test case mapping guide (for 03-test-plan.md)

| AC type | Test approach (write in plan, activate at Stage 0) |
|---------|---------------------------------------------------|
| Route accessible to role X | `role-url-audit` suite entry — HTTP 200 for role X |
| Route blocked for role Y | `role-url-audit` suite entry — HTTP 403 for role Y |
| Form / E2E user flow | Playwright suite — new test or extend existing |
| Content visible / not visible | Crawl + role audit entry |
| Permission check | `qa-permissions.json` rule + role audit entry |

See full process: `runbooks/intake-to-qa-handoff.md`

## Acceptance Criteria (attached below)

# Acceptance Criteria (PM-owned)

## Gap analysis reference

All criteria below are `[NEW]` — no existing character class system implementation exists in dungeoncrawler_content. Dev builds from scratch. Can be built in parallel with dc-cr-ancestry-system and dc-cr-background-system.

## Happy Path
- [ ] `[NEW]` A `character_class` content type exists with fields: name, description, key_ability (one or more ability scores), hit_points_per_level (int), proficiencies (saves: Fortitude/Reflex/Will each with trained/expert/master/legendary; attacks: unarmed/simple/martial/advanced/ranged; defenses: unarmored/light/medium/heavy), and class_features (list of feature names with level granted).
- [ ] `[NEW]` All 12 core PF2E classes are seeded as content: alchemist, barbarian, bard, champion, cleric, druid, fighter, monk, ranger, rogue, sorcerer, wizard.
- [ ] `[NEW]` A character creation step accepts a class selection and stores it on the character entity.
- [ ] `[NEW]` Selecting a class sets the character's HP-per-level contribution from class (e.g., fighter: 10, wizard: 6, cleric: 8).
- [ ] `[NEW]` Selecting a class applies the class's proficiency levels to the character's proficiency record.
- [ ] `[NEW]` Selecting a class records the class's 1st-level class features on the character entity.

## Edge Cases
- [ ] `[NEW]` A character cannot have more than one class (multiclassing is out of scope for MVP); re-selecting class replaces prior choice, removing prior class's HP/level and proficiency grants before applying new class.
- [ ] `[NEW]` Attempting to save a character without a class selection during the class creation step returns a clear validation error ("Class is required").
- [ ] `[NEW]` A class with multiple key ability options (e.g., champion: Str or Dex) prompts the player to choose one; selection is required before proceeding.

## Failure Modes
- [ ] `[NEW]` An invalid class ID passed to the creation endpoint returns 400 with a descriptive error.
- [ ] `[NEW]` Missing key ability selection when a class offers a choice is caught before save with a clear message ("You must choose a key ability for this class").

## Permissions / Access Control
- [ ] Anonymous user behavior: character_class content type nodes are publicly readable; application to a character requires authentication.
- [ ] Authenticated user behavior: players may select a class for their own character only; cannot modify another player's character.
- [ ] Admin behavior: admins can create, edit, and delete character_class content type nodes.

## Data Integrity
- [ ] No data loss: an existing character with a class set must retain that data after module updates.
- [ ] Rollback path: uninstalling the class sub-module must leave character nodes intact (class reference and derived fields become empty/null, not corrupted).

## Verification method
```
drush php-eval "
  \$count = \Drupal::entityQuery('node')->condition('type','character_class')->count()->execute();
  print 'Class count: ' . \$count . PHP_EOL; // expect 12
"
```
- QA automated audit must still pass (0 violations, 0 failures) after module changes are deployed to local.

## Knowledgebase check
- KB: none found for character class system specifically.
- `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md` — run `drush cr` + QA audit immediately after module changes.
- Dependency note: `dc-cr-character-creation` depends on ancestry, background, AND class all being implemented before the end-to-end creation workflow can be built. These three features are independent of each other and can be developed in parallel.
