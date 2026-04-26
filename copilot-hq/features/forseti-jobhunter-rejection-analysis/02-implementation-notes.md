# Implementation Notes: forseti-jobhunter-rejection-analysis

- Feature: forseti-jobhunter-rejection-analysis
- Author: dev-jobhunter
- Date: 2026-04-25
- Status: implemented

## Summary

The rejection-analysis feature is already implemented in the `job_hunter`
module. Users can close/reject an application with a constrained rejection
reason and optional notes, and the analytics page renders both a rejection
reasons summary and a stage heat-map when enough staged rejection data exists.

## Implemented surfaces

- Schema support in `job_hunter.install`
  - `user_closed_status`
  - `rejection_reason`
  - `rejection_notes`
- Close/reject UI and read-only detail view in
  `src/Controller/CompanyController.php`
- CSRF-protected save handler with ownership checks and sanitized notes in
  `src/Controller/CompanyController.php`
- Analytics summary + stage heat-map in
  `src/Controller/ApplicationSubmissionController.php`
