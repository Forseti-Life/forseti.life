# Test Plan: forseti-jobhunter-salary-tracker

- Feature: forseti-jobhunter-salary-tracker
- Author: qa-forseti
- Date: 2026-04-19

## Test cases

| ID | Scenario | Steps | Expected |
|----|----------|-------|----------|
| TC-01 | Expectation fields saved | Submit saved-job form with min/max/currency | Values stored in DB |
| TC-02 | Offer delta displayed | Job has expectation + offer with salary | Offer detail shows salary-delta element |
| TC-03 | Delta hidden — no expectation | Job has offer but no expectation | No salary-delta element |
| TC-04 | Delta hidden — no offer salary | Job has expectation but offer lacks salary | No salary-delta element |
| TC-05 | Analytics table rendered | At least one closed job with both expectation and offer | salary-comparison section on /jobhunter/analytics |
| TC-06 | Fields optional — no error | Submit saved-job form with blank expectation | Form saves; fields NULL in DB |
| TC-07 | Ownership enforced | User B cannot read/write user A's salary expectation | 403 or empty |
| TC-08 | CSRF enforced | POST without CSRF token | 403 response |
| TC-09 | Unauthenticated blocked | GET/POST without session | Redirect to login |
