# Acceptance Criteria: forseti-jobhunter-salary-tracker

- Feature: forseti-jobhunter-salary-tracker
- Module: job_hunter
- Author: pm-forseti
- Date: 2026-04-19

## Summary

Record salary expectation at application time and compare to actual offer amounts. Show delta on offer detail and in analytics.

## Acceptance criteria

### AC-1: Salary expectation fields on saved-job add/edit form

**Given** an authenticated user on the saved-job add or edit form,
**When** they enter a salary expectation range (min and/or max) and a currency code (optional, default USD),
**Then** the values are persisted to `salary_expectation_min`, `salary_expectation_max`, and `salary_currency` on `jobhunter_saved_jobs`.

**Verify:** `drush sql:query "SELECT salary_expectation_min, salary_expectation_max, salary_currency FROM jobhunter_saved_jobs WHERE uid=<uid> ORDER BY id DESC LIMIT 1"` → expected values.

---

### AC-2: Offer detail shows expectation vs actual delta

**Given** a saved job has both a salary expectation and a recorded offer (salary fields on `jobhunter_offers`),
**When** the user views the offer detail page,
**Then** a summary line shows: expectation range, offer range, and numeric delta (offer_min - expectation_min).

**Verify:** `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/my-jobs/{nid}/offers/{oid} | grep -q 'salary-delta'`

---

### AC-3: Delta section hidden when either value is absent

**Given** a saved job has no salary expectation OR the offer has no salary,
**When** the user views the offer detail,
**Then** the salary delta section is not rendered.

**Verify:** page HTML does not contain `class="salary-delta"` for that case.

---

### AC-4: Analytics dashboard salary comparison table

**Given** the user has at least one closed application with both an expectation and an offer,
**When** the user loads `/jobhunter/analytics`,
**Then** a salary comparison section lists each job with expectation, offer, and delta columns.

**Verify:** `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/analytics | grep -q 'salary-comparison'`

---

### AC-5: Fields are optional; no validation error when blank

**Given** the saved-job form,
**When** the user submits without filling in expected salary,
**Then** the form saves successfully; expectation fields are NULL.

---

## Security acceptance criteria

### SEC-1: Authentication required
All salary routes require `_user_is_logged_in: 'TRUE'`.

### SEC-2: Ownership enforcement
Controller verifies `saved_job_id` belongs to `currentUser()->id()` before any read or write.

### SEC-3: CSRF on POST
All save/update POST endpoints use Drupal's CSRF token pattern.

### SEC-4: No PII in logs
Exact salary figures are not logged to watchdog; log only `uid` and `saved_job_id`.
