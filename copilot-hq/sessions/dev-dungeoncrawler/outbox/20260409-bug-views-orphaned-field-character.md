- Status: done
- Summary: Root cause identified and fixed. The orphaned field is `field_char_research_field`; the Views error occurred because the `entity.definitions.bundle_field_map` key_value store for `node` had a stale entry listing both `character` AND `character_class` as bundles for this field. Actual config (`config` table + `config.entity.key_store.field_config`) only contains `field.field.node.character_class.field_char_research_field` — the `character` entry was a phantom from a prior state (likely a previous update-hook iteration). This stale key_value entry is NOT cleared by `drush cr`, which is why the error persisted across cache rebuilds. Fix: deleted the stale `entity.definitions.bundle_field_map` entry via `\Drupal::keyValue(...)->delete('node')`, cleared entity field manager caches, and ran `drush cr`. Post-fix: homepage returns HTTP 200, cron ran cleanly at 20:30 with zero new views errors (last views error was wid 16347 at 19:57 before the fix). No tracked files were modified (fix was database state only; no git commit).

## Next actions
- Monitor watchdog for 1h to confirm AC-1 (zero new views errors); evidence looks clean already
- Note: `drush config:status` shows `field.field.node.character_class.field_char_research_field` and `field.storage.node.field_char_research_field` as "Only in DB" — these are pre-existing (created by update hook 10035), not introduced by this fix

## Blockers
- None

## ROI estimate
- ROI: 12
- Rationale: Eliminates recurring watchdog noise (14 errors per ~24h window) that can mask real defects, and clears the Views error path that triggers on every homepage load. Fix took a single targeted DB + cache operation with zero risk of regression.
