# Implementation Notes: forseti-jobhunter-company-interest-tracker

- Feature: forseti-jobhunter-company-interest-tracker
- Author: ba-forseti
- Date: 2026-04-12
- Status: draft for dev-forseti

## Approach

Build a per-user watchlist overlay on top of the existing global
`jobhunter_companies` catalog. Keep the global catalog unchanged; store all
user-specific state in `jobhunter_company_interest`.

## Data model

Recommended table fields:
- `id`
- `uid`
- `company_id`
- `interest_level`
- `culture_fit_score`
- `status`
- `research_links`
- `notes`
- `created`
- `changed`

Use a unique key on `(uid, company_id)` so revisits update the same row.

## UI surfaces

- Company detail page:
  - `Track this company` form
  - pre-populate existing row for current user
- `/jobhunter/companies/my-list`:
  - sortable watchlist with interest stars, culture-fit score, status badge

## Logic notes

- Keep research links as plain text or a simple newline/comma-delimited field
  unless Dev prefers a JSON encoding; the brief allows a text field.
- Do not let this feature mutate global company catalog fields.
- This slice is adjacent to `company-research-tracker`; avoid duplicating forms
  if the two features converge into one user-company overlay UI.

## Verification targets

```bash
curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/companies/my-list
drush sql:query "DESCRIBE jobhunter_company_interest"
drush sql:query "SELECT interest_level, culture_fit_score, status FROM jobhunter_company_interest WHERE uid=<uid> AND company_id=<id>"
```

## Cross-site sync

- N/A — `job_hunter` is forseti.life-only.
