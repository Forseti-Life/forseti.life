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
