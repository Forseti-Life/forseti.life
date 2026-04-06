# Implementation Notes (Dev-owned)
# Feature: dc-cr-character-class

## Summary
All NEW. `CharacterManager::CLASSES` constant exists with full class data (name, hp_per_level, key_ability, saves, attacks, defenses, class_features). No `character_class` Drupal content type currently exists. First slice creates the content type config + install hook seeding all 12 core PF2E classes. Can proceed in parallel with dc-cr-ancestry-system and dc-cr-background-system.

## Impact Analysis
- New `character_class` node type; no conflict with existing types.
- Seeding reads from `CharacterManager::CLASSES` — no duplication of data.
- Character creation step 3 in `CharacterCreationStepForm` handles class selection; will need wiring to new content type nodes.
- `buildCharacterJson()` in `CharacterManager` already reads class data from the CLASSES constant to set HP and proficiencies — that logic is not changed; the new node type is an authoritative content layer on top.

## Files / Components Touched
- `dungeoncrawler_content/config/install/node.type.character_class.yml`
- Field storage configs: name (title), description, key_ability (list_string multi-value), field_class_hp_per_level (int), field_class_proficiencies (text serialized JSON or structured fields), field_class_features (text long — JSON list of {level, name})
- `dungeoncrawler_content/dungeoncrawler_content.install` — seed 12 classes

## Data Model / Storage Changes
- Schema updates: new `character_class` node type
- Config changes: new fields
- Migrations: none — class stored as string in character JSON; node reference is optional enhancement

## First code slice
1. Create `node.type.character_class.yml` + field configs.
2. Seed 12 classes in install/update hook from `CharacterManager::CLASSES`.
3. Verify: `drush php-eval "print Drupal::entityQuery('node')->condition('type','character_class')->count()->execute();"` → expect `12`.

## Security Considerations
- Input validation: class nodes admin-only writable; public read.
- Access checks: class application requires auth (enforced in CharacterCreationStepForm).
- Sensitive data handling: none.

## Testing Performed
- Commands run: (pending implementation)
- Targeted scenarios:
  - Count check (12)
  - Fighter: hp_per_level=10; Wizard: hp_per_level=6
  - Champion key_ability = [Str, Dex] (two options — player must choose one)
  - Selection stores class on character JSON with correct HP/proficiency values

## Rollback / Recovery
- Revert commit. Existing characters unaffected (class stored as string, not node reference by default).

## Knowledgebase references
- `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md` — run `drush cr` after config install.
- Dependency note: dc-cr-character-creation must not start until ancestry, background, AND class are all merged.

## What I learned (Dev)
- (pending)

## What I'd change next time (Dev)
- (pending)
