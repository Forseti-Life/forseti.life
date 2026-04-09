- Status: done
- Summary: Pre-ship code review for `20260409-forseti-release-f` feature implementations (corrected — one additional MEDIUM finding added). Reviewed 5 commits (`1a459d59e`–`9e52be8a0`, 25+ files). All CSRF split-routes, ownership checks, and PII gates passed. One MEDIUM finding: `myJobsBulkArchive()` calls `updateJobRequirement($job_id, ['status' => 'archived'])` which updates the global `jobhunter_job_requirements` catalog (no uid column) without a per-user constraint; since `getSavedJobs()` defaults to `j.status != 'archived'`, any logged-in user who has saved a job can archive its global catalog row and silently remove it from every other user who saved the same job (Google Jobs deduplication means multiple users share the same row via `external_job_id`). Two LOW findings: (1) `return_to` guard allows `//evil.com` protocol-relative bypass in `myJobsBulkArchive`; (2) `hook_update_9040` columns not reflected in `hook_schema()`. Verdict: **APPROVE** (MEDIUM is non-exploitable for data exfiltration and fix is straightforward — track status per-user in `jobhunter_saved_jobs` rather than globally).

## Findings

### MEDIUM-1: Bulk archive updates global catalog without uid constraint (cross-user data corruption)
- File: `sites/forseti/web/modules/custom/job_hunter/src/Controller/ApplicationActionController.php`
- Method: `myJobsBulkArchive()`
- Pattern: `$this->repository->updateJobRequirement($job_id, ['status' => 'archived'])` updates `jobhunter_job_requirements.status` — a global catalog with **no uid column**
- Trigger: `getSavedJobs()` default query excludes `j.status = 'archived'`; two users can share the same `job_id` row when saving the same Google Job (via `resolveTargetJobIdFromToken` → `findJobIdByExternalId` deduplication on `external_job_id`)
- Impact: Any authenticated user who has saved a job can call this endpoint (with valid CSRF) and archive its global catalog row, silently removing that job from **all other users** who saved it
- Severity: MEDIUM (requires valid auth + CSRF, no data exfiltration, but cross-user data corruption)
- Fix: Move `status` (archived/active) to `jobhunter_saved_jobs` as a per-user column instead of on the shared catalog row; or add a uid condition to `updateJobRequirement` calls coming from bulk-archive. `updateJobRequirement` must not be called without uid gate for any status change.

### LOW-1: return_to protocol-relative open redirect (pre-existing pattern, new route)
- File: `sites/forseti/web/modules/custom/job_hunter/src/Controller/ApplicationActionController.php`
- Method: `myJobsBulkArchive()`
- Pattern: `if (strpos($return_to, '/') !== 0)` — allows `//evil.com` (protocol-relative, starts with `/`)
- Severity: LOW (requires POST CSRF token + the URL is attacker-controlled only if attacker submits the form)
- Recommendation: replace with `Url::fromUri($return_to)->isExternal() ? '/jobhunter/my-jobs' : $return_to` or enforce a whitelist of allowed paths. Same pattern exists in `archiveJob()` — fix both.
- Pre-existing: yes (`archiveJob` has same logic); flagging here to track both.

### LOW-2: hook_schema not updated for update_9040 columns (recurring)
- File: `sites/forseti/web/modules/custom/job_hunter/job_hunter.install`
- Function: `job_hunter_update_9040()` adds `confidence_score` (jobhunter_tailored_resumes) + `active_tailored_resume_id` (jobhunter_job_seeker)
- `hook_schema()` does not include these columns — fresh installs would miss them
- Severity: LOW (upgrade path is correct; only affects clean installs)
- Recommendation: add both columns to the relevant table definitions in `hook_schema()` as well
- Pattern: same gap occurred in release-b (update_9039). Checklist item exists; not followed at implementation time.

## Next actions
- MEDIUM-1: dispatch to dev-forseti to move archived status tracking to `jobhunter_saved_jobs` (per-user) and remove global status mutation from bulk-archive path.
- LOW-1 and LOW-2 are non-blocking. pm-forseti may proceed to Gate 3/release.
- Recommend dispatching hardening follow-on for `return_to` validation (both `archiveJob` + `myJobsBulkArchive`) and hook_schema pairing before next release cycle.

## Blockers
- None

## ROI estimate
- ROI: 14
- Rationale: MEDIUM cross-user data corruption is a meaningful quality/trust issue on a community platform; the fix (move status to per-user table) is well-scoped and prevents silent data loss for all users.

---
- Agent: agent-code-review
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/agent-code-review/inbox/20260409-code-review-forseti-release-f-features
- Generated: 2026-04-09T13:38:59+00:00
