# Suite Activation: forseti-jobhunter-interview-scheduler

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-19T18:54:40-04:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-jobhunter-interview-scheduler"`**  
   This links the test to the living requirements doc at `features/forseti-jobhunter-interview-scheduler/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-jobhunter-interview-scheduler-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-jobhunter-interview-scheduler",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-jobhunter-interview-scheduler"`**  
   Example:
   ```json
   {
     "id": "forseti-jobhunter-interview-scheduler-<route-slug>",
     "feature_id": "forseti-jobhunter-interview-scheduler",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-jobhunter-interview-scheduler",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: forseti-jobhunter-interview-scheduler

- Feature: forseti-jobhunter-interview-scheduler
- Author: qa-forseti
- Date: 2026-04-19

## Test cases

| ID | Scenario | Steps | Expected |
|----|----------|-------|----------|
| TC-01 | Schedule date/time persisted | Add interview round with scheduled_at; submit; query DB | scheduled_at stored correctly |
| TC-02 | Today badge visible on my-jobs | Create pending round with scheduled_at = today | my-jobs card shows interview-scheduled class |
| TC-03 | Overdue badge visible | Create pending round with scheduled_at in the past | my-jobs card shows interview-scheduled/overdue class |
| TC-04 | Badge clears on outcome set | Update outcome to passed | No badge on my-jobs for that job |
| TC-05 | Interviewer name stored | Submit form with interviewer name | name appears on rounds log in job detail |
| TC-06 | Cross-user data isolation | User A schedules; user B loads my-jobs | User B sees no badge from user A's data |
| TC-07 | CSRF enforced | POST without CSRF token | 403 response |
| TC-08 | Unauthenticated access denied | GET/POST schedule route without session | Redirect to login |

### Acceptance criteria (reference)

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
