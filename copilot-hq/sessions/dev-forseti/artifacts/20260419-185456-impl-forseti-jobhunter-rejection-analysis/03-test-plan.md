# Test Plan: forseti-jobhunter-rejection-analysis

- Feature: forseti-jobhunter-rejection-analysis
- Author: qa-forseti
- Date: 2026-04-19

## Test cases

| ID | Scenario | Steps | Expected |
|----|----------|-------|----------|
| TC-01 | Rejection reason saved | Set status to rejected, select reason; submit | rejection_reason stored in DB |
| TC-02 | Rejection notes optional | Set status to rejected with no notes; submit | Form saves; notes NULL |
| TC-03 | Reason not required for non-rejected | Submit with active status, no reason | No validation error |
| TC-04 | Reason required when status is rejected | Submit rejected status with no reason | Validation error shown |
| TC-05 | Notes displayed on job detail | Save notes; load job detail | Notes shown read-only |
| TC-06 | Analytics table — counts per reason | Multiple rejections with reasons | rejection-reasons section on /jobhunter/analytics |
| TC-07 | Stage chart hidden below threshold | Fewer than 5 stage rejections | No rejection-stage-chart element |
| TC-08 | Stage chart shown at threshold | 5+ stage rejections | rejection-stage-chart element present |
| TC-09 | Ownership enforced | User B cannot read user A's rejection data | Empty / no cross-user data |
| TC-10 | CSRF enforced | POST without CSRF token | 403 response |
| TC-11 | Unauthenticated blocked | GET/POST without session | Redirect to login |
