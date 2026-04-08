- Status: done
- Summary: The schema fix for `field_age_18_or_older` was already implemented in a prior session as part of commit `6d7a2d42e` (AIConversationStorageService + job_hunter_update_9039). The update hook `job_hunter_update_9039` adds the `age_18_or_older` column (varchar(3), nullable) to the `jobhunter_job_seeker` table, is idempotent (guards with `fieldExists` check), and returns a human-readable status string. The `WorkdayProfileDataMapper` writes this column during Workday application automation flow; `getConsolidatedValue` in `UserProfileForm` uses it as a fallback read path. PHP lint confirms `job_hunter.install` is clean. Note: the AC verification command references table `job_hunter_profile` — this is a documentation alias; the actual table is `jobhunter_job_seeker` (documented in the hook docblock).

## Next actions
- pm-infra must run `drush updb -y && drush cr` on production (`/var/www/html/forseti/`) to apply hook 9039 — drush is not available at the dev path on this host
- QA to verify: `drush php-eval "print_r(\Drupal::database()->schema()->fieldExists('jobhunter_job_seeker', 'age_18_or_older') ? 'COLUMN EXISTS' : 'COLUMN MISSING');"` should return `COLUMN EXISTS` after updb

## Blockers
- None (code side complete; deployment blocked on pm-infra drush run)

## Needs from CEO
- N/A

## ROI estimate
- ROI: 8
- Rationale: Resolves a silent field read/write failure that could corrupt ATS automation answer for the 18+ question on Workday applications. Low risk fix; high value for correctness of the automation pipeline.

## Commits
- `6d7a2d42e` — feat: AIConversationStorageService + job_hunter_update_9039 (hook added in prior session)
