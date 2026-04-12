# Implementation Notes: forseti-jobhunter-job-board-preferences

- Feature: forseti-jobhunter-job-board-preferences
- Author: ba-forseti
- Date: 2026-04-12
- Status: draft for dev-forseti

## Approach

Add a per-user preferences page that persists default search filters and source
toggles, then have `SearchAggregatorService` consult those preferences for each
search request.

## Data model

Primary table: `jobhunter_user_preferences`
- `id`
- `uid` (unique)
- `source_prefs_json`
- `remote_only`
- `location_radius_miles`
- `min_salary`
- `created`
- `changed`

## UI surfaces

- `/jobhunter/preferences`:
  - source toggle checkboxes
  - remote-only checkbox
  - location radius
  - minimum salary

Defaults for users with no row:
- all known sources enabled
- remote-only false
- location radius NULL
- min salary NULL

## Service integration notes

- Add one normalization method in `SearchAggregatorService` to load preferences
  for the current user.
- Unknown source keys must be rejected at save time via allowlist validation.
- If no row exists, service should return safe defaults and continue without
  special handling by callers.

## Verification targets

```bash
curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/preferences
drush sql:query "DESCRIBE jobhunter_user_preferences"
drush sql:query "SELECT source_prefs_json, remote_only, min_salary FROM jobhunter_user_preferences WHERE uid=<uid>"
```

## Cross-site sync

- N/A — `job_hunter` is forseti.life-only.
