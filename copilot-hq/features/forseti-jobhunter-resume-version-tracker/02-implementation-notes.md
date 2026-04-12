# Implementation Notes: forseti-jobhunter-resume-version-tracker

- Feature: forseti-jobhunter-resume-version-tracker
- Author: ba-forseti
- Date: 2026-04-12
- Status: draft for dev-forseti

## Approach

Extend the existing application/resume model rather than introducing a new
tracker table. The feature brief already points to a clean schema change on
`jobhunter_applications`.

## Schema changes

Add to `jobhunter_applications`:
- `submitted_resume_id` INT NULL
- `submitted_resume_type` VARCHAR(16) NULL

No historical backfill is required.

## Ownership model

- Base resumes come from `jobhunter_job_seeker_resumes`
- Tailored resumes come from `jobhunter_tailored_resumes`
- Controller should normalize both sources into one selection list for the
  current user, then persist the chosen id/type pair onto the application row

## UI surfaces

- Application or saved-job detail:
  - display currently linked resume
  - allow user to select/update linked resume
- Resume detail view:
  - show `Used in applications`
  - link back to job/application records using that version

## Logic notes

- Keep `submitted_resume_type` explicit instead of inferring from id collisions.
- Prefer nullable columns over sentinel values.
- Reuse existing resume names/labels; do not add new authoring workflow here.

## Verification targets

```bash
drush sql:query "DESCRIBE jobhunter_applications"
drush sql:query "SELECT submitted_resume_id, submitted_resume_type FROM jobhunter_applications WHERE uid=<uid> AND job_id=<id>"
```

## Cross-site sync

- N/A — `job_hunter` is forseti.life-only.
