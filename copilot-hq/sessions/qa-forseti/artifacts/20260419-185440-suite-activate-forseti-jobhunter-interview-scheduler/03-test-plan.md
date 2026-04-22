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
