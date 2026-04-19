# Suite Activation: forseti-jobhunter-salary-tracker

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-19T18:54:56-04:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-jobhunter-salary-tracker"`**  
   This links the test to the living requirements doc at `features/forseti-jobhunter-salary-tracker/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-jobhunter-salary-tracker-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-jobhunter-salary-tracker",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-jobhunter-salary-tracker"`**  
   Example:
   ```json
   {
     "id": "forseti-jobhunter-salary-tracker-<route-slug>",
     "feature_id": "forseti-jobhunter-salary-tracker",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-jobhunter-salary-tracker",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

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

### Acceptance criteria (reference)

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
