# Feature: forseti-jobhunter-salary-tracker

- Work item id: forseti-jobhunter-salary-tracker
- Module: job_hunter
- Status: in_progress
- Priority: high
- Release: 20260412-forseti-release-m
- Author: pm-forseti
- Date: 2026-04-19

## Summary

Allow users to record a salary expectation when applying to a job and compare it against actual offer amounts. Surface salary expectation vs offer delta on the offer detail view and on the analytics dashboard.

## Value statement

As a job hunter, I want to track my salary expectations against actual offers so I can calibrate my negotiation strategy and understand whether my expectations align with market.

## Technical notes

- Add `salary_expectation_min` and `salary_expectation_max` (DECIMAL 10,2, nullable) to `jobhunter_saved_jobs`.
- Compare against `salary_min` / `salary_max` already on `jobhunter_offers` (if present; add if not).
- Delta widget on offer detail: "Expectation: $X–$Y | Offered: $A–$B | Delta: $Δ".
- Analytics page: chart or table of expectation vs offer across all closed applications with offers.
- Currency code stored as a char(3) field (ISO 4217) with default `USD`.

## Security acceptance criteria

- Authentication/permission surface: All salary routes require authenticated user (`_user_is_logged_in: 'TRUE'`).
- CSRF expectations: All save/update POST endpoints use Drupal's CSRF token pattern.
- Input validation: Salary min/max validated as numeric DECIMAL; currency validated against ISO 4217 whitelist.
- PII/logging constraints: Exact salary figures must not be logged to watchdog; log only uid and saved_job_id.

## Out of scope
- Multi-currency conversion; just store and display the user's currency code.
- Tax calculations.
