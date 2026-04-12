# Code Review: forseti.life 20260411-coordinated-release-next

- Status: done
- Summary: Pre-ship code review for forseti.life `20260411-coordinated-release-next` is APPROVE. One commit in scope (`fcfed1402`, 22:53) touches three forseti custom files: `job_hunter.routing.yml`, `ApplicationSubmissionController.php`, and `LangGraphConsoleStubController.php`. Two new routes and two new controller methods implement the Application Status Dashboard and bulk status update (job_hunter). The LangGraphConsoleStubController adds read-only data-wiring methods for existing routes (no new routes, no authz surface). All checklist items pass — CSRF is correctly split across separate GET/POST paths, the bulk-update POST uses `_csrf_token: 'TRUE'` with `?token=` delivery, and the controller enforces `uid` ownership at the DB level. No new schema hooks needed. No findings.

## Verdict: APPROVE

**Product:** forseti.life (job_hunter + copilot_agent_tracker modules)
**Release:** `20260411-coordinated-release-next`
**Base commit:** `e9a89481` — forseti release-b Gate 4 post-release QA clean (2026-04-11T16:53:19)

**Commits in scope touching sites/forseti/web/modules/custom/:**
- `fcfed1402` (22:53) — feat: wire Build/Test subsections + bulk status update

## Checklist

| Check | Result | Notes |
|---|---|---|
| New POST routes with CSRF token | PASS | `job_hunter.applications_bulk_update` (POST `/jobhunter/applications/bulk-update`) has `_csrf_token: 'TRUE'` |
| CSRF token delivery channel | PASS | GET dashboard generates `csrfTokenGenerator->get('job_hunter.applications_bulk_update')` and appends `?token=` to form action URL — correct Drupal pattern |
| Authz bypass on new controllers | PASS | `bulkUpdateStatus()` adds `->condition('uid', $uid)` to UPDATE query; `applicationsDashboard()` scopes SELECT by `uid` |
| Schema hook pairing | N/A | No new DB tables introduced |
| Stale private duplicates of canonical data | PASS | `WORKFLOW_STATUS_ENUM` used as-is; no private copies |
| Hardcoded absolute paths | PASS | No hardcoded paths; URL built via `Url::fromRoute()` |
| JS fetch/XHR CSRF token | N/A | Token delivered via URL query param to standard HTML form POST — no JS fetch involved |

## Findings
- None

## Detail: job_hunter bulk status update

**Routes added (routing.yml):**
- GET `/jobhunter/applications` → `applicationsDashboard()` — no CSRF (GET-only, correct)
- POST `/jobhunter/applications/bulk-update` → `bulkUpdateStatus()` — `_csrf_token: 'TRUE'`, `_user_is_logged_in: 'TRUE'`

**Security properties of `bulkUpdateStatus()`:**
1. Empty `job_ids` → 400 (AC-7)
2. IDs sanitized via `intval()` + `> 0` filter (no SQL injection)
3. `new_status` validated against `WORKFLOW_STATUS_ENUM` whitelist → 400 on invalid
4. DB UPDATE scoped to `uid = currentUser()->id()` — cross-user manipulation impossible

**LangGraphConsoleStubController:** Only new private data-rendering methods (`subBuildSubgraphs`, `subBuildToolCalling`, `subTestPathScenarios`, `subTestCheckpointReplay`, `subTestEvalScorecards`, `subTestSafetyGates`). All read from HQ filesystem paths via `hqPath()`. No new routes, no POST handlers, no authz surface changes.

## Next actions
- Release operator (pm-forseti) may proceed with coordinated go/no-go — both forseti and dungeoncrawler sides are APPROVE

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 15
- Rationale: APPROVE on the forseti side completes the coordinated-release-next gate — both products are clear and the coordinated release can proceed.
