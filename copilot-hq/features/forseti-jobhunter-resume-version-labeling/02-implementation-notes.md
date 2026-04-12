# Implementation Notes: forseti-jobhunter-resume-version-labeling

- Feature: forseti-jobhunter-resume-version-labeling
- Author: ba-forseti
- Date: 2026-04-12
- Status: draft for dev-forseti

## Approach

Extend the existing resume-management workflow with lightweight metadata fields
and a source-resume link on applications. This feature is the labeling-focused
precursor to the broader `resume-version-tracker` reporting slice.

## Schema changes

Add to `jobhunter_job_seeker_resumes`:
- `version_label` VARCHAR(120) NULL
- `version_notes` TEXT NULL

Add to `jobhunter_applications`:
- `source_resume_id` INT NULL

## UI surfaces

- Resume management page:
  - edit version label and notes inline
  - show `Used in N applications`
- Application submission flow:
  - persist selected source resume into `source_resume_id`

## Logic notes

- Duplicate labels are allowed, but should trigger a warning message rather than
  a hard validation failure.
- Keep label/notes storage plain text only.
- `source_resume_id` should reference base uploaded resumes only; the broader
  base-vs-tailored distinction belongs to `resume-version-tracker`.

## Relationship to resume-version-tracker

- `resume-version-labeling` establishes labels and the base-resume linkage.
- `resume-version-tracker` adds the explicit submitted-resume type and
  where-used reporting across base and tailored resumes.
- Shared helpers for resume selection/counts should be factored if both ship.

## Verification targets

```bash
drush sql:query "DESCRIBE jobhunter_job_seeker_resumes"
drush sql:query "DESCRIBE jobhunter_applications"
drush sql:query "SELECT version_label, version_notes FROM jobhunter_job_seeker_resumes WHERE id=<id>"
```

## Cross-site sync

- N/A — `job_hunter` is forseti.life-only.
