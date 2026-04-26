# Implementation Notes: forseti-jobhunter-salary-tracker

- Feature: forseti-jobhunter-salary-tracker
- Author: dev-jobhunter
- Date: 2026-04-25
- Status: implemented

## Summary

The salary-tracker feature is already implemented in the `job_hunter` module.
Users can save salary expectations on saved jobs, the offer detail view renders
an expectation-versus-offer delta when both values are present, and the
analytics page shows a salary comparison section across offers.

## Implemented surfaces

- Schema support in `job_hunter.install`
  - `salary_expectation_min`
  - `salary_expectation_max`
  - `salary_currency`
- Salary expectation form + AJAX save flow in
  `src/Controller/CompanyController.php`
- Offer detail delta widget in `src/Controller/CompanyController.php`
- Analytics salary comparison section in
  `src/Controller/ApplicationSubmissionController.php`
