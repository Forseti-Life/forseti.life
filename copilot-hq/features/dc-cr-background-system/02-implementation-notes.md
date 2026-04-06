# Implementation Notes (Dev-owned)
# Feature: dc-cr-background-system

## Summary
All NEW. `CharacterManager::BACKGROUNDS` constant exists with full background data (name, fixed boost, free boost, skill training, lore, feat). No `background` Drupal content type currently exists. First slice creates the content type config + install hook seeding 5+ core backgrounds from the BACKGROUNDS constant. Can proceed in parallel with dc-cr-ancestry-system and dc-cr-character-class.

## Impact Analysis
- New `background` node type; no conflict with existing types.
- Seeding reads from `CharacterManager::BACKGROUNDS` constant — no duplication.
- Character creation step 2 in `CharacterCreationStepForm` handles background selection; that form will need to be wired to the new content type nodes (currently may use PHP constants directly).

## Files / Components Touched
- `dungeoncrawler_content/config/install/node.type.background.yml` — new content type config
- Field storage configs: name (title), description, fixed_boost (list_string), free_boost (note string), skill_training (string), lore_skill (string), skill_feat (string)
- `dungeoncrawler_content/dungeoncrawler_content.install` — seed 5 core backgrounds (Acolyte, Acrobat, Animal Whisperer, Artisan, Barkeep)

## Data Model / Storage Changes
- Schema updates: new `background` node type with fields per AC spec
- Config changes: none beyond new node type + fields
- Migrations: none for existing characters (background stored as string in character JSON)

## First code slice
1. Create `node.type.background.yml` + field configs in `config/install/`.
2. In `dungeoncrawler_content_install()` or `_update_N()`: iterate BACKGROUNDS, create at least 5 core background nodes.
3. Verify: `drush php-eval "print Drupal::entityQuery('node')->condition('type','background')->count()->execute();"` → expect `>= 5`.

## Security Considerations
- Input validation: background nodes are admin-only writable; public read.
- Access checks: background application to a character requires auth (enforced in CharacterCreationStepForm).
- Sensitive data handling: none.

## Testing Performed
- Commands run: (pending implementation)
- Targeted scenarios:
  - Count check (>= 5)
  - Background selection in creation form stores `background.name` and boosts on character JSON
  - Duplicate boost validation rejects fixed+free pointing to same ability

## Rollback / Recovery
- Revert commit. Character nodes retain background string; no corruption on rollback.

## Knowledgebase references
- `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md` — run `drush cr` after config install.
- Dependency note: dc-cr-character-creation depends on this feature being done first.

## What I learned (Dev)
- (pending)

## What I'd change next time (Dev)
- (pending)
