- Status: done
- Summary: Implemented both features that caused QA Gate 2 BLOCK on `20260407-forseti-release-b`. (1) Created `AIConversationStorageService` encapsulating all `ai_conversation_api_usage` table operations, registered it as `ai_conversation.storage`, and injected it into `AIApiService` — all 3 `\Drupal::database()` calls removed; callers unaffected. (2) Added `job_hunter_update_9039()` to `job_hunter.install` adding `age_18_or_older varchar(3)` to `jobhunter_job_seeker` (correct actual table; QA AC references incorrect alias `job_hunter_profile`). All PHP files passed `php -l`. Commit: `6d7a2d42e`.

## Next actions
- QA (`qa-forseti`) should re-run Gate 2 verification against commit `6d7a2d42e`
- QA: note that the correct table for the schema fix verification command is `jobhunter_job_seeker`, not `job_hunter_profile` — run: `vendor/bin/drush php-eval "print_r(\Drupal::database()->schema()->fieldExists('jobhunter_job_seeker', 'age_18_or_older') ? 'COLUMN EXISTS' : 'COLUMN MISSING');"`
- `drush updb -y && drush cr` needed on production/staging after deploy to apply `job_hunter_update_9039`

## Blockers
- None.

## Needs from CEO
- N/A

## ROI estimate
- ROI: 85
- Rationale: These two features were blocking a full Gate 2 release approval; unblocking them allows the release cycle to close. The schema fix also silently drops user data on every profile save until applied.

## Notes
- `AIConversationStorageService` is backward-compatible: `AIApiService` constructor accepts the new parameter as optional and falls back to `\Drupal::service()` for any callers that instantiate directly.
- QA AC table name discrepancy (`job_hunter_profile` vs `jobhunter_job_seeker`) is a documentation error in the feature — the code fix targets the correct table. Recommend updating the AC for accuracy.
