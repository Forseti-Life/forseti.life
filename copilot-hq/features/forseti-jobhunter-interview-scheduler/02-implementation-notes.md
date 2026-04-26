# Implementation Notes: forseti-jobhunter-interview-scheduler

- Feature: forseti-jobhunter-interview-scheduler
- Author: dev-jobhunter
- Date: 2026-04-25
- Status: implemented

## Summary

The interview scheduler is already implemented in the `job_hunter` module.
Users can persist `scheduled_at` and `interviewer_name` on interview rounds,
and `/jobhunter/my-jobs` renders "Interview today" / "Interview overdue"
badges for pending scheduled interviews.

## Implemented surfaces

- Schema support in `job_hunter.install`
  - `scheduled_at`
  - `interviewer_name`
- Interview round save/load logic in `src/Controller/CompanyController.php`
- Badge derivation in `src/Controller/ApplicationSubmissionController.php`
- Badge UI in `templates/my-jobs.html.twig`

## Notes

- The feature still needs QA follow-through from the active `qa-jobhunter`
  queue to confirm suite coverage and final release readiness.
