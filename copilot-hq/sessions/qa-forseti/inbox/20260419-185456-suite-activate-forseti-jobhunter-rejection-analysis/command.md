# Suite Activation: forseti-jobhunter-rejection-analysis

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
   **CRITICAL: tag every new entry with `"feature_id": "forseti-jobhunter-rejection-analysis"`**  
   This links the test to the living requirements doc at `features/forseti-jobhunter-rejection-analysis/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-jobhunter-rejection-analysis-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-jobhunter-rejection-analysis",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-jobhunter-rejection-analysis"`**  
   Example:
   ```json
   {
     "id": "forseti-jobhunter-rejection-analysis-<route-slug>",
     "feature_id": "forseti-jobhunter-rejection-analysis",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-jobhunter-rejection-analysis",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

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

### Acceptance criteria (reference)

# Acceptance Criteria: forseti-jobhunter-rejection-analysis

- Feature: forseti-jobhunter-rejection-analysis
- Module: job_hunter
- Author: pm-forseti
- Date: 2026-04-19

## Summary

Categorize rejection reasons on closed/rejected applications and aggregate them into the analytics dashboard.

## Acceptance criteria

### AC-1: Rejection reason field on application close

**Given** an authenticated user sets a saved-job status to `rejected` or `closed`,
**When** the form is submitted,
**Then** a required-if-rejected `rejection_reason` select field is shown and the chosen value persisted.

Allowed values: `no_response`, `resume_screen`, `phone_screen`, `technical_screen`, `interview_round`, `offer_declined_by_company`, `offer_declined_by_me`, `position_cancelled`, `other`.

**Verify:** `drush sql:query "SELECT rejection_reason FROM jobhunter_saved_jobs WHERE uid=<uid> ORDER BY id DESC LIMIT 1"` → matches submitted value.

---

### AC-2: Optional rejection notes

**Given** the rejection reason field is filled in,
**When** the user also enters free-form notes (nullable TEXT),
**Then** the notes are saved alongside the reason and displayed on the job detail view (read-only).

**Verify:** `drush sql:query "SELECT rejection_notes FROM jobhunter_saved_jobs WHERE uid=<uid> ORDER BY id DESC LIMIT 1"` → expected text.

---

### AC-3: Rejection reason not required when status is not rejected/closed

**Given** an application with any other status (active, pending, offer, etc.),
**When** the form is submitted without a rejection reason,
**Then** no validation error is thrown and the form saves successfully.

---

### AC-4: Analytics rejection-reasons summary table

**Given** the user has at least one rejected/closed application with a reason,
**When** the user loads `/jobhunter/analytics`,
**Then** a "Rejection Reasons" section shows a count-per-reason breakdown.

**Verify:** `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/analytics | grep -q 'rejection-reasons'`

---

### AC-5: Stage heat-map (if ≥5 rejections with stages)

**Given** the user has 5 or more rejected applications with stage-level data (phone_screen, technical_screen, interview_round),
**When** the user loads `/jobhunter/analytics`,
**Then** a "Rejection by Stage" bar chart or table is rendered showing counts per stage.

**Verify:** chart element with class `rejection-stage-chart` is present only when data threshold is met.

---

## Security acceptance criteria

### SEC-1: Authentication required
All rejection routes require `_user_is_logged_in: 'TRUE'`.

### SEC-2: Ownership enforcement
Controller verifies `saved_job_id` belongs to `currentUser()->id()` before any read or write.

### SEC-3: CSRF on POST
All form submissions use Drupal's form API CSRF protection.

### SEC-4: No PII in logs
Free-form `rejection_notes` text is not logged to watchdog; log only `uid` and `saved_job_id`.
