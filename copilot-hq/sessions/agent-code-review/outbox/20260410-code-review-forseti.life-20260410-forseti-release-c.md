# Code Review — forseti.life release 20260410-forseti-release-c

- Status: done
- Summary: Reviewed 28 commits (base `bd012cdfd`..HEAD) touching 41 files across job_hunter, ai_conversation, and copilot_agent_tracker. One HIGH finding blocks ship: `interview-prep-page.html.twig` delivers the `_csrf_token: 'TRUE'` token in the POST body (`form_token=...`) instead of the URL query string (`?token=`), which will produce a 403 on every AI tips fetch. Two LOW findings do not block. All other checklist items are CLEAN. Verdict: **BLOCK** — fix the HIGH finding before re-review.

---

## Verdict: BLOCK

| # | Severity | File | Issue |
|---|---|---|---|
| 1 | HIGH | `interview-prep-page.html.twig` | CSRF token in POST body — functional 403 on every AI tips call |
| 2 | LOW | `forseti-conversations.html.twig:71` | Dead `form_token` hidden field (path() already appends `?token=`) |
| 3 | LOW | `DashboardController.php:41` | Hardcoded fallback path `RELEASE_CYCLE_CONTROL_FILE_LEGACY` |

---

## Finding 1 — HIGH (BLOCKS SHIP)

**File:** `sites/forseti/web/modules/custom/job_hunter/templates/interview-prep-page.html.twig` (lines ~78–90)

**Route:** `job_hunter.interview_prep_ai_tips` — `POST /jobhunter/interview-prep/{job_id}/ai-tips` — `_csrf_token: 'TRUE'`

**Problem:** The template's JavaScript sends the CSRF token in the POST body:
```js
body: 'form_token=' + encodeURIComponent(csrfToken),
```
`CsrfAccessCheck::access()` reads `$request->query->get('token')` (URL query string only). It never inspects the POST body. Every `fetch()` call to the ai-tips route will return `403 Access Denied`.

**Root cause:** Same class of bug as the dead body-field pattern cleaned up in release-b, except here it is the *only* delivery path (there is no `?token=` in the fetch URL), so the endpoint is completely unreachable.

**Fix pattern:**
```js
// Append ?token= to the URL; do NOT put it in the body.
var aiTipsUrl = {{ ai_tips_url|json_encode|raw }};
var csrfToken = {{ csrf_token(ai_tips_url)|json_encode|raw }};
var urlWithToken = aiTipsUrl + '?token=' + encodeURIComponent(csrfToken);

fetch(urlWithToken, {
  method: 'POST',
  headers: { 'X-Requested-With': 'XMLHttpRequest' },
  // body stays empty or carries only payload data — no form_token
})
```

Note: `csrf_token(ai_tips_url)` uses `ai_tips_url` as the seed. `CsrfAccessCheck` validates against `$request->getRequestUri()`. Ensure `ai_tips_url` passed to the filter is the bare path without query string (e.g., `/jobhunter/interview-prep/5/ai-tips`) so the seeds match at validation time. Alternatively, use `path('job_hunter.interview_prep_ai_tips', {'job_id': job_id})` in Twig (RouteProcessorCsrf auto-appends the correct `?token=`) and pass that URL directly to `fetch()`.

---

## Finding 2 — LOW (non-blocking)

**File:** `sites/forseti/web/modules/custom/ai_conversation/templates/forseti-conversations.html.twig:71`

```twig
{% set delete_url = path('forseti.conversation_delete', {'conversation_id': conv.id}) %}
<form method="post" action="{{ delete_url }}">
  <input type="hidden" name="form_token" value="{{ csrf_token(delete_url) }}">
```

`path()` calls through `RouteProcessorCsrf::processOutbound()`, which appends `?token=` to `delete_url` because `forseti.conversation_delete` has `_csrf_token: 'TRUE'`. The hidden `form_token` field is dead code — `CsrfAccessCheck` never reads the POST body. No security impact (CSRF is correctly enforced via the URL). Cleanup recommended in next dev cycle.

---

## Finding 3 — LOW (non-blocking)

**File:** `sites/forseti/web/modules/custom/copilot_agent_tracker/src/Controller/DashboardController.php:41`

```php
const RELEASE_CYCLE_CONTROL_FILE_LEGACY = '/home/ubuntu/forseti.life/copilot-hq/tmp/release-cycle-control.json';
```

Used only as a fallback when the primary path (env var → `RELEASE_CYCLE_CONTROL_FILE_DEFAULT`) returns empty. Admin-only page (`administer copilot agent tracker`), so no data exposure risk to users. Recommend replacing with an env var fallback or `COPILOT_HQ_ROOT`-relative path in a follow-up.

---

## CLEAN checklist

| Item | Result |
|---|---|
| New POST routes have `methods:[POST]` | CLEAN — 8 new POST routes all have `methods: [POST]` |
| New POST routes have `_csrf_token: 'TRUE'` | CLEAN — all 8 have it |
| CSRF delivery channel (no dead body fields on new templates) | **BLOCK** — interview-prep ai-tips fetch uses body (Finding 1); LOW dead field in conversations (Finding 2) |
| Authorization bypass — ownership checks | CLEAN — all new methods (coverLetter, coverLetterGenerate, coverLetterSave, interviewPrep, interviewPrepSave, interviewPrepAiTips, savedSearchDelete, conversationDelete, conversationExport) validate `uid == currentUser()->id()` |
| Schema hook pairing (hook_install + hook_update_N) | CLEAN — update_9040–9043 present; hook_install() updated with 2 new table helpers |
| `hook_schema()` returns [] (intentional) | CLEAN — established pattern |
| Return-to open redirect | CLEAN — 6 instances fixed with `preg_match('/^\/(?!\/)/', $return_to)` |
| Hardcoded paths | LOW — `RELEASE_CYCLE_CONTROL_FILE_LEGACY` constant (Finding 3) |
| Stale private duplicates | CLEAN — no duplicate controller logic detected |

---

## Next actions
- Dev-forseti inbox item dispatched: `20260410-fix-aitips-csrf-delivery-forseti-release-c` (ROI 50)
- After dev fixes `interview-prep-page.html.twig`, re-review that single file and issue APPROVE if clean

## Blockers
- None — findings are documented, ship is blocked pending Finding 1 fix

## Needs from CEO
- N/A

## ROI estimate
- ROI: 50
- Rationale: Finding 1 is a functional regression that makes the interview AI tips feature completely broken (100% 403 failure rate). Blocking ship protects user experience and avoids a production hotfix cycle. Fast-path resolution: dev changes one template file (~3 lines).
