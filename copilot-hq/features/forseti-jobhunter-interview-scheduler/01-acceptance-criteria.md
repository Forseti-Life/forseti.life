# Acceptance Criteria: forseti-jobhunter-interview-scheduler

- Feature: forseti-jobhunter-interview-scheduler
- Module: job_hunter
- Author: pm-forseti
- Date: 2026-04-19

## Summary

Add per-interview scheduling fields to the existing `jobhunter_interview_rounds` table (or a new `jobhunter_interview_schedule` table keyed by `uid, saved_job_id`). Allow users to pre-schedule upcoming interviews with a date, time, type, and optional interviewer name. Flag scheduled interviews that are today or overdue on `/jobhunter/my-jobs`.

## Acceptance criteria

### AC-1: Schedule date/time field on interview-round form

**Given** an authenticated user on the interview-round add/edit form for a saved job,
**When** they fill in a scheduled date and time,
**Then** the values are persisted to a `scheduled_at` datetime field on the round record (new column on `jobhunter_interview_rounds` or separate schedule row).

**Verify:** `drush sql:query "SELECT scheduled_at FROM jobhunter_interview_rounds WHERE uid=<uid> ORDER BY id DESC LIMIT 1"` → returns the stored datetime.

---

### AC-2: Upcoming interview badge on /jobhunter/my-jobs

**Given** a saved job has an interview round with `scheduled_at` = today or in the past AND `outcome = pending`,
**When** the user loads `/jobhunter/my-jobs`,
**Then** that job card shows a visible "Interview today" or "Interview overdue" badge.

**Verify:** `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/my-jobs | grep -q 'interview-scheduled'`

---

### AC-3: Badge clears when outcome is set

**Given** an interview round has a `scheduled_at` in the past AND `outcome` is updated to `passed`, `failed`, or `withdrawn`,
**When** the user loads `/jobhunter/my-jobs`,
**Then** no scheduled-interview badge is shown for that job.

**Verify:** update outcome to `passed`; reload — no `interview-scheduled` class on that card.

---

### AC-4: Optional interviewer name field

**Given** the interview-round form,
**When** the user enters an interviewer name (varchar 255, optional),
**Then** it is stored on the round record and displayed in the rounds log on the job detail view.

**Verify:** `drush sql:query "SELECT interviewer_name FROM jobhunter_interview_rounds WHERE uid=<uid> ORDER BY id DESC LIMIT 1"` → returns the stored name.

---

### AC-5: Schedule data is scoped to current user

**Given** user A has a scheduled interview for saved_job_id 10,
**When** user B views their job list,
**Then** user B sees no badge or data from user A's scheduled interview.

**Verify:** `drush sql:query "SELECT COUNT(*) FROM jobhunter_interview_rounds WHERE uid=<uid_B> AND scheduled_at IS NOT NULL"` → 0 (if user B has none).

---

## Security acceptance criteria

### SEC-1: Authentication required
All interview-schedule routes require `_user_is_logged_in: 'TRUE'`.

### SEC-2: CSRF on POST routes
All save/update endpoints use the split-route CSRF pattern.

### SEC-3: Ownership enforcement
Controller verifies the `saved_job_id` and `interview_round_id` belong to `currentUser()->id()` before any read or write.

### SEC-4: PII-safe logging
Do not log `interviewer_name` to watchdog. Log only `uid` and `interview_round_id`.
