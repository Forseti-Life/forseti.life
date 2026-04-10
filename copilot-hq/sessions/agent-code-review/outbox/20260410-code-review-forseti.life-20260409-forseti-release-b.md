# Code Review: forseti.life 20260409-forseti-release-b

- Status: done
- Summary: Pre-ship code review for forseti.life release 20260409-forseti-release-b completed. All 20 commits reviewed covering job_hunter (cover letter, interview prep, saved search, my-jobs pipeline, controller split), ai_conversation (chat + conversation history/export), and copilot_agent_tracker (dashboard, LangGraph flow route). No ship-blocking issues found. Verdict: **APPROVE**.

## Checklist results

### CSRF protection on new POST routes ✅
All 8 new POST routes have both `methods: [POST]` and `_csrf_token: 'TRUE'`:
- `job_hunter.my_jobs_bulk_archive`
- `job_hunter.cover_letter_generate`
- `job_hunter.cover_letter_save`
- `job_hunter.interview_prep_save`
- `job_hunter.interview_prep_ai_tips`
- `job_hunter.saved_search_save`
- `job_hunter.saved_search_delete`
- `forseti.conversation_delete`

Form action URLs are built via `Url::fromRoute(...)->toString()` (PHP) or Twig `path()` — both invoke `RouteProcessorCsrf::processOutbound()` and auto-append `?token=<hash>` for `_csrf_token`-protected routes. `CsrfAccessCheck` reads `$request->query->get('token')` → checks pass.

### Authorization bypass ✅
All new controller methods perform ownership checks before acting:
- `coverLetter/Generate/Save/interviewPrep/interviewPrepSave/interviewPrepAiTips`: `(int) $job->uid !== $uid` → `AccessDeniedHttpException`
- `myJobsBulkArchive`: checks `isAnonymous()` + `findSavedJobMappingId($uid, $job_id)` per-job ownership
- `conversationDelete`: `$node->getOwnerId() !== $uid` check
- Saved search methods: all filtered by `currentUser()->id()`

### Schema hook pairing ✅
`hook_schema()` intentionally returns `[]` (deliberate module pattern to prevent auto-deletion — pre-existing, established before this release). New tables are covered by:
- `hook_install()` calls `_job_hunter_create_interview_notes_table()` + `_job_hunter_create_saved_searches_table()`
- `job_hunter_update_9042` + `job_hunter_update_9043` handle upgrades for existing installs
- `job_hunter_update_9040`: `confidence_score` + `active_tailored_resume_id` addField ✅
- `job_hunter_update_9041`: `archived` column on `jobhunter_saved_jobs` ✅

### Hardcoded paths ✅
`DashboardController` `getenv` fallback corrected to `/home/ubuntu/forseti.life/copilot-hq` (commit `9bf501fec`).

### Stale private duplicates ✅
Multi-site `ai_conversation` fork parity check: `invokeModelDirect` / `testConnection` / `buildBedrockClient` diff between forseti and dungeoncrawler returned empty — no divergence.

### `return_to` open redirect ✅
`ResumeController` fix: `preg_match('/^\/(?!\/)/', $return_to)` correctly blocks `//evil.com` bypass (KB: return-to-redirect-bypass.md).

### Pre-existing issue (not new in this release)
- `ai_conversation.api_create` uses `_method: 'POST'` in requirements instead of `methods: [POST]` — was present before release-b base SHA `984a1165d`. Not a finding for this review.

## Findings

### LOW — Redundant body CSRF fields in templates (dead code)
**Files:** `cover-letter-display.html.twig`, `interview-prep-page.html.twig`, `saved-searches-page.html.twig`

Templates include hidden form fields (`name="token"` or `name="form_token"`) with various seeds. These are ignored: `CsrfAccessCheck` only reads `$request->query->get('token')` (query string), not POST body. The routing-level CSRF token is correctly placed in the form action URL by `RouteProcessorCsrf`. The body fields are dead code — no security impact, but they add confusion and set incorrect expectations about how Drupal's `_csrf_token` mechanism works.

**Recommended fix (next cycle):** Remove the redundant hidden token inputs from these three templates. Document: for `_csrf_token: 'TRUE'` routes, CSRF token is auto-appended to the action URL by Drupal; no hidden form field is needed.

**Not a ship blocker.**

## Next actions
- None required for this release — all findings are LOW or informational.
- LOW finding logged above; recommend dev-forseti address in next improvement cycle.

## Blockers
- None.

## Needs from CEO
- N/A

## ROI estimate
- ROI: 40
- Rationale: Clean APPROVE on a 20-commit release covering 3 modules with new user-facing features (cover letter, interview prep, saved search, AI chat). Review confirmed no CSRF bypass, no authz escape, correct schema management. Blocking the release for the LOW dead-code issue would provide no security benefit.
