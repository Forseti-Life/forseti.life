# Implementation Notes: forseti-jobhunter-contact-tracker

- Feature: forseti-jobhunter-contact-tracker
- Author: ba-forseti
- Date: 2026-04-12
- Status: draft for dev-forseti

## Approach

Implement a per-user contact CRM with `jobhunter_contacts` as the primary table
and surface relevant contacts on saved-job detail views by matching company.

## Data model

Recommended fields:
- `id`
- `uid`
- `name`
- `company_id`
- `title`
- `relationship_type`
- `last_contact_date`
- `referral_status`
- `notes`
- `created`
- `changed`

Prefer a nullable FK-style `company_id` if some historical contacts do not yet
map cleanly to the company catalog, but preserve the brief's intent to match
saved jobs by company.

## UI surfaces

- `/jobhunter/contacts`:
  - add/edit/delete list page
  - empty state for new users
- saved-job detail:
  - `Your contacts here` section showing matching contacts for the job's company

## Logic notes

- Keep delete as an explicit confirmation flow.
- All list/detail queries must be uid-scoped.
- This feature overlaps with `contact-referral-tracker`; if both stay live,
  converge on one shared `jobhunter_contacts` implementation instead of two
  separate tables.

## Verification targets

```bash
curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/contacts
drush sql:query "DESCRIBE jobhunter_contacts"
drush sql:query "SELECT name, company_id, relationship_type FROM jobhunter_contacts WHERE uid=<uid>"
```

## Cross-site sync

- N/A — `job_hunter` is forseti.life-only.
