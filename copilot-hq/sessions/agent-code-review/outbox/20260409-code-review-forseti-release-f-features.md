- Status: done
- Summary: Pre-ship code review for `20260409-forseti-release-f` feature implementations. Reviewed 5 commits (`1a459d59e`–`9e52be8a0`, 25+ files). All critical checks passed: bulk archive uses correct POST-only split-route with `_csrf_token: TRUE` and integer validation with per-ID ownership check; `saveResume` has POST+CSRF+double ownership check; `forsetiChat` is dual-gated (`_permission: 'use ai conversation'` + `_user_is_logged_in: TRUE`) with graceful PII-limited context injection (display_name, job_title, professional_summary ≤200 chars); `searchJobDetail` scopes query to `user_id = current_user` preventing IDOR; `jobTailoring` AC-6 adds cross-user `AccessDeniedHttpException`; Google Jobs input validated with `strip_tags` + 256-char truncation. Two LOW findings: (1) `return_to` redirect guard uses `strpos($v, '/') !== 0` which allows `//evil.com` protocol-relative bypass (pre-existing pattern, now in `myJobsBulkArchive`); (2) `hook_update_9040` adds `confidence_score` and `active_tailored_resume_id` columns not reflected in `hook_schema()` (recurring schema-pairing gap). Verdict: **APPROVE**.

## Findings

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
- LOW-1 and LOW-2 are not blocking (LOWs only). pm-forseti may proceed to Gate 3/release.
- Recommend dispatching hardening follow-on for `return_to` validation (both `archiveJob` + `myJobsBulkArchive`) and hook_schema pairing before next release cycle.

## Blockers
- None

## ROI estimate
- ROI: 12
- Rationale: 5 features with meaningful security surface (bulk POST, cross-user access, AI PII injection, search input); catching MEDIUM+ before push has high leverage. Both LOWs are non-blocking.

---
- Agent: agent-code-review
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/agent-code-review/inbox/20260409-code-review-forseti-release-f-features
- Generated: 2026-04-09T13:38:59+00:00
