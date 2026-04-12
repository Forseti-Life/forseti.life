# Implementation Notes: forseti-jobhunter-follow-up-reminders

- Feature: forseti-jobhunter-follow-up-reminders
- Author: ba-forseti
- Date: 2026-04-12
- Status: draft for dev-forseti

## Approach

Implement the smallest durable reminder model that supports inline job-list
badges without introducing notification infrastructure.

## Storage decision

The feature brief allows either:

1. a new `jobhunter_follow_ups` table keyed by `(uid, saved_job_id)`, or
2. a `follow_up_date` column on `jobhunter_saved_jobs`.

**Recommendation:** prefer a dedicated `jobhunter_follow_ups` table if the
existing saved-jobs schema is already busy with multiple optional tracking
features. Prefer a column on `jobhunter_saved_jobs` only if Dev confirms that
table is the canonical owner for all per-job tracking metadata.

## UI surfaces

- Saved-job detail view:
  - add/edit/clear a `Follow-up by` date
- `/jobhunter/my-jobs`:
  - render `follow-up-overdue` badge when:
    - follow-up date < today
    - status is still pre-response / `applied`

## Logic notes

- Overdue status should be derived at render time, not stored.
- Badge should clear automatically when:
  - date is NULL
  - date >= today
  - job status progresses to phone screen or beyond

## Verification targets

```bash
curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/my-jobs
drush sql:query "SELECT follow_up_date FROM jobhunter_follow_ups WHERE uid=<uid> AND saved_job_id=<id>"
```

If the column approach is chosen, replace the query with
`jobhunter_saved_jobs.follow_up_date`.

## Cross-feature notes

- This feature should integrate cleanly with `application-notes`, but should not
  depend on notes shipping first.
- No notification/email plumbing belongs in this v1.

## Cross-site sync

- N/A — `job_hunter` is forseti.life-only.
