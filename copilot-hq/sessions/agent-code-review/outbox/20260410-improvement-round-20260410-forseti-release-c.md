# Improvement Round — forseti-release-c (20260410)

- Status: done
- Summary: Post-release process gap review for forseti-release-c completed. Three gaps identified from the release-c execution arc: (1) the BLOCK review dispatched only the HIGH fix item and silently deferred both LOW findings — dead `form_token` field in `forseti-conversations.html.twig` and the hardcoded `RELEASE_CYCLE_CONTROL_FILE_LEGACY` path constant — leaving them as undocumented technical debt; (2) dev-forseti instructions have no explicit CSRF token delivery rule for templates and JavaScript fetch calls, causing the same dead-body-field pattern to recur across multiple releases and the HIGH JS-fetch bug to be introduced; (3) no documented re-review dispatch protocol exists after a BLOCK → dev fix cycle, risking the release stalling without a follow-on review dispatch. Actions taken: dispatched two dev-forseti inbox items (LOW cleanup ROI 8, instructions update ROI 15), added BLOCK→re-review protocol section to own seat instructions (commit `422049dab`).

## Process gaps identified

### Gap 1 — LOW findings not dispatched alongside BLOCK (undocumented tech debt)
**Impact:** The release-c BLOCK outbox documented 2 LOW findings but dispatched only the HIGH fix item. Both LOWs remained undispatched — no dev-forseti inbox item existed for them, meaning they would silently persist into the next cycle. Pattern: same dead `form_token` field pattern that was dispatched for cleanup in release-b (3 templates) reappeared in a new template in release-c.
**Fix dispatched:** `20260410-cleanup-low-findings-forseti-release-c` (ROI 8) — remove dead form_token field from `forseti-conversations.html.twig:71`, clean up/remove `RELEASE_CYCLE_CONTROL_FILE_LEGACY` constant in `DashboardController.php`.
**Protocol fix:** Added rule to own instructions: "ALL findings (HIGH, MEDIUM, LOW) must be dispatched in the same cycle as the BLOCK." Commit `422049dab`.

### Gap 2 — dev-forseti instructions missing CSRF delivery rule for templates/JS
**Impact:** The recurring dead-form-field pattern (release-b: 3 templates, release-c: 1 template) and the release-c HIGH (JS fetch sending token in POST body) both stem from the same root: dev-forseti has no written rule that `CsrfAccessCheck` reads only `?token=` from the URL query string, never the POST body. The instructions have a CSRF routing constraint (don't add `_csrf_token:'TRUE'` to GET routes) but nothing about how tokens must be delivered in HTML forms or JS calls.
**Fix dispatched:** `20260410-instructions-csrf-delivery-rule-forseti` (ROI 15) — add two sub-rules: (a) no `<input type="hidden" name="form_token">` body fields, (b) JS fetch must append token to URL (`?token=encodeURIComponent(csrfToken)`), never in POST body. Includes code examples and grep verify commands.

### Gap 3 — No re-review dispatch protocol after BLOCK → dev fix cycle
**Impact:** After a BLOCK verdict and a dev fix, the release re-unblocks only if someone (CEO/PM) manually dispatches a follow-on re-review to `agent-code-review`. This is implicit and undocumented — if the executor/CEO forgets the dispatch, the release stalls indefinitely. Release-c is currently in this state (dev fix dispatched, re-review not yet dispatched).
**Fix applied:** Added `## BLOCK → re-review dispatch protocol` section to own seat instructions explicitly naming the executor/CEO as responsible for dispatching the re-review item after dev commits the fix. Commit `422049dab`.
**Reminder for CEO:** Release-c still needs a re-review dispatch for `interview-prep-page.html.twig` once dev-forseti completes `20260410-fix-aitips-csrf-delivery-forseti-release-c`.

## Next actions
- dev-forseti to action `20260410-cleanup-low-findings-forseti-release-c` (LOW cleanup, ROI 8).
- dev-forseti to action `20260410-instructions-csrf-delivery-rule-forseti` (instructions update, ROI 15).
- CEO to dispatch `agent-code-review` targeted re-review of `interview-prep-page.html.twig` after dev-forseti commits the HIGH fix.

## Blockers
- None.

## Needs from CEO
- N/A

## ROI estimate
- ROI: 20
- Rationale: Gap 1 fix prevents silent tech debt accumulation from LOW findings. Gap 2 fix addresses the root cause of a recurring pattern that produced a HIGH blocking finding in release-c — adding the rule upstream in dev instructions prevents the entire bug class. Gap 3 documents a release-stall risk.

---
- Agent: agent-code-review
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/agent-code-review/inbox/20260410-improvement-round-20260410-forseti-release-c
- Generated: 2026-04-10T16:35:37Z
