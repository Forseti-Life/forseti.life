# Feature: forseti-jobhunter-rejection-analysis

- Work item id: forseti-jobhunter-rejection-analysis
- Module: job_hunter
- Status: done
- Priority: medium
- Release: 20260412-forseti-release-n
- Author: pm-forseti
- Date: 2026-04-19

## Summary

Allow users to categorize the reason when an application is rejected or closed without offer. Aggregate rejection reasons in the analytics dashboard to surface patterns (most common reasons, stages where rejections occur most).

## Value statement

As a job hunter, I want to categorize and review why applications were rejected so I can identify patterns and improve my approach.

## Technical notes

- Add `rejection_reason` ENUM (or varchar with a constrained list) and `rejection_notes` TEXT (nullable) columns to `jobhunter_saved_jobs` or a dedicated `jobhunter_rejection_log` table.
- Rejection reason list: `no_response`, `resume_screen`, `phone_screen`, `technical_screen`, `interview_round`, `offer_declined_by_company`, `offer_declined_by_me`, `position_cancelled`, `other`.
- Set only when application status transitions to `rejected` or `closed`.
- Analytics `/jobhunter/analytics`: bar chart or table — count per rejection reason + heat-map by stage (if data is sufficient).

## Security acceptance criteria

- Authentication/permission surface: All rejection routes require authenticated user (`_user_is_logged_in: 'TRUE'`).
- CSRF expectations: All form submissions use Drupal's form API CSRF protection.
- Input validation: rejection_reason validated against the allowed ENUM list server-side; rejection_notes sanitized as plain text.
- PII/logging constraints: Free-form rejection_notes must not be logged to watchdog; log only uid and saved_job_id.

## Out of scope
- AI-based pattern analysis.
- Comparison across users.

- PM owner: pm-jobhunter

- Dev owner: dev-jobhunter

- QA owner: qa-jobhunter
