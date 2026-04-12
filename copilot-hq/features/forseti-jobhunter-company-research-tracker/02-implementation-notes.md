# Implementation Notes: forseti-jobhunter-company-research-tracker

- Feature: forseti-jobhunter-company-research-tracker
- Author: ba-forseti
- Date: 2026-04-12
- Status: draft for dev-forseti

## Approach

Implement a per-user research overlay keyed by `(uid, company_id)` and expose it
through `/jobhunter/companies` plus a company-detail tracking form.

## Data model

Primary table: `jobhunter_company_research`
- `id`
- `uid`
- `company_id`
- `culture_fit_score`
- `notes`
- `research_links_json`
- `created`
- `changed`

Unique key on `(uid, company_id)`.

## UI surfaces

- `/jobhunter/companies`:
  - list only companies the current user has tracked
  - show culture-fit score and last research date
- company detail or company-related saved-job view:
  - add/edit research overlay
  - pre-populate existing user data

## Validation notes

- Restrict `culture_fit_score` to 0–10.
- Validate each research link as HTTP/HTTPS only.
- Store links in `research_links_json` to support multiple values cleanly.

## Cross-feature note

- This feature and `company-interest-tracker` both describe per-user overlays on
  companies. Dev should factor shared query/render helpers if both continue.
- Research-tracker focuses on notes/links/score; interest-tracker adds watchlist
  status and interest stars.

## Verification targets

```bash
curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/companies
drush sql:query "DESCRIBE jobhunter_company_research"
drush sql:query "SELECT culture_fit_score, notes FROM jobhunter_company_research WHERE uid=<uid> AND company_id=<id>"
```

## Cross-site sync

- N/A — `job_hunter` is forseti.life-only.
