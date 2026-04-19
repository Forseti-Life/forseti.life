# Feature Brief

- Work item id: forseti-jobhunter-interview-scheduler
- Website: forseti.life
- Module: job_hunter
- Status: in_progress
- Release: 20260412-forseti-release-m
- Feature type: new-feature
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Priority: high
- Source: pm-forseti grooming 2026-04-19

## Summary

Users can log interview rounds via `interview-outcome-tracker`, but there is no way to schedule upcoming interviews in advance. This feature adds a scheduled-interview layer to the `jobhunter_interview_rounds` table: users set a future date/time and optional interviewers list on an interview slot before it occurs. The `/jobhunter/my-jobs` view flags jobs with upcoming interviews today or overdue (past-scheduled, still pending). Helps users prepare in advance and remember interview commitments across many concurrent applications.

## User story

As a job seeker managing multiple active applications, I want to log upcoming interview dates and times per job so that my job list highlights today's and overdue interviews at a glance, and I always know what I'm prepping for next.

## Non-goals

- Calendar sync or iCal export (separate feature)
- Email/push notification reminders (separate feature)
- Multi-invitee or recruiter coordination
- Video call link embedding

## Acceptance criteria

See `01-acceptance-criteria.md`

## Security acceptance criteria

- All routes require `_user_is_logged_in: 'TRUE'`
- CSRF split-route pattern on all POST routes
- All DB reads/writes scoped to `currentUser()->id()` — no uid from request
- No PII (interviewer names) logged to watchdog; log only uid and interview_id
