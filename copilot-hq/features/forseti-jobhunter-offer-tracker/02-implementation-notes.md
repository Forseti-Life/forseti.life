# Implementation Notes: forseti-jobhunter-offer-tracker

- Feature: forseti-jobhunter-offer-tracker
- Author: ba-forseti
- Date: 2026-04-12
- Status: draft for dev-forseti

## Approach

Add an offer sub-view to the saved-job detail workflow rather than creating a
standalone CRUD system first. The comparison page at `/jobhunter/offers` should
be a read-only aggregation of the user's existing offer rows.

## Data model

Create `jobhunter_offers` keyed effectively by `(uid, saved_job_id)` with:
- `id`
- `uid`
- `saved_job_id`
- `base_salary`
- `equity_summary`
- `benefits_summary`
- `response_deadline`
- `notes`
- `created`
- `changed`

Recommend a unique key on `(uid, saved_job_id)` to keep the feature idempotent.

## UI surfaces

- Saved-job detail:
  - render offer form only when status is `offer received` (or equivalent live status)
  - allow create/update in place
- `/jobhunter/offers`:
  - list/compare all active offers
  - show empty-state when offer count < 2

## Logic notes

- Do not couple this feature to reminders. Response deadline display is enough
  for v1; notification behavior belongs elsewhere.
- If the job status falls out of `offer received`, keep the stored offer row but
  decide whether the comparison page filters it out or marks it inactive.
- Reuse saved-job joins for company and role labels instead of duplicating them
  into `jobhunter_offers`.

## Verification targets

```bash
curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/offers
drush sql:query "DESCRIBE jobhunter_offers"
drush sql:query "SELECT COUNT(*) FROM jobhunter_offers WHERE uid=<uid>"
```

## Cross-site sync

- N/A — `job_hunter` is forseti.life-only.
