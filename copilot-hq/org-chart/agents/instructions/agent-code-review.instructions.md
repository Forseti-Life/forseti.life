# Agent Instructions: agent-code-review

## Authority
This file is owned by the `agent-code-review` seat.

## Owned file scope (source of truth)
- Read-only review by default.

### HQ repo: /home/ubuntu/forseti.life/copilot-hq
- sessions/agent-code-review/**
- org-chart/agents/instructions/agent-code-review.instructions.md

## Required ownership reference
- Use `org-chart/DECISION_OWNERSHIP_MATRIX.md` to classify issue types before escalating.
- This seat is a "capability agent" per the matrix — resolve discovery/review outputs directly; escalate ownership decisions to supervisor.

## Out-of-scope rule
- Deliver findings via outbox; do not patch files outside owned scope unless explicitly delegated.
- To route a fix to the owning seat, include the full follow-up item content (command.md + roi.txt) in the outbox for the executor to create.

## Idle behavior (aligned with org-wide directive 2026-02-22)
- Do NOT create new inbox items "just to stay busy".
- Do NOT queue follow-up work items autonomously.
- Perform a small refactor/review within owned scope and write findings in outbox.
- If action is needed on findings, escalate to PM supervisor with Status: needs-info and ROI.

## Review checklist (apply to every script/file reviewed)
Before the findings table, run and record each check as applies/N/A:
- [ ] Missing file/arg existence guards (unhandled FileNotFoundError, empty-var usage)
- [ ] Unhandled subprocess exit codes swallowed by `|| true`
- [ ] Log directory placement (tmp/logs/ not inbox/responses/)
- [ ] GNU-only filesystem calls (find -printf, stat -c %Y) — portability
- [ ] Duplicated logic — extract to shared lib when pattern repeats
- [ ] Silent `|| true` on critical path (consume-forseti-replies, idle-work-generator, etc.)
- [ ] Hardcoded absolute paths or environment-specific values
- [ ] Idempotency: partial-creation leftover state, directory-vs-file guards
- [ ] Drupal-specific: `_csrf_token: 'TRUE'` must NOT be added to routes with GET in their methods list — run `grep -A3 '<route>' routing.yml | grep methods` before flagging CSRF gaps (2026-03-22: addposting regression in forseti CSRF patch)
- [ ] Drupal-specific CSRF route-path seed: `CsrfAccessCheck` validates `?token=` against the **rendered route path** (without leading slash, with raw parameter values substituted). E.g., route `jobhunter/my-jobs/{job_id}/applied` with `job_id=5` → seed must be `'jobhunter/my-jobs/5/applied'`. Any custom seed string (e.g., `'job_apply_5'`) will produce 403 on every submission. Verify: `grep -n 'csrfToken.*get\(' <controller>` and confirm the seed matches the route path. (2026-04-08: FR-RB-01 — JobApplicationController + CompanyController used `'job_apply_{id}'` custom seeds, all submissions 403'd)
- [ ] Drupal-specific: stale private duplicates of canonical data — check if controller/service has hardcoded lookups that diverge from a `const` or `static` in a Manager/Service class (2026-03-22: CharacterCreationController::getAncestryTraits() vs CharacterManager::ANCESTRIES)
- [ ] Drupal-specific: schema hook pairing — for any new/modified table, verify both `hook_schema()` AND `hook_update_N()` exist; if only `hook_schema()` is present, the table will exist on fresh install but not on upgrade deployments (2026-04-05: dc_chat_sessions and dc_campaign_characters.version missing in production — hooks defined but not run; review `.install` file)
- [ ] Drupal-specific (dungeoncrawler): new routes must be pre-registered in `org-chart/sites/dungeoncrawler/qa-permissions.json` in the same commit as routing.yml — verify with `git show <impl-commit> -- org-chart/sites/dungeoncrawler/qa-permissions.json | grep diff` (2026-03-22: false-positive QA violation cycle from unregistered `/dungeoncrawler/traits` in release-next)
- [ ] Drupal-specific (dungeoncrawler): every new POST route MUST have `_csrf_request_header_mode: TRUE` in its requirements — verify with `grep -A8 '<new-route-name>:' routing.yml | grep csrf` (2026-03-27: inventory_sell_item shipped without it while all other POST inventory routes had it)
- [ ] Authorization bypass on optional override params — any `$gm_override`, `$admin_override`, or similar bypass flag accepted from request body MUST be gated on a permission check before use (2026-03-27: gm_override in sellItem() accepted from any authenticated user)
- [ ] Multi-site module fork parity — when reviewing ai_conversation or any module duplicated across forseti and dungeoncrawler, verify all SDK/helper construction paths use the centralized helper in BOTH copies (not inline reconstruction in one). Check `invokeModelDirect()`, `testConnection()`, and any method that calls external APIs directly. (2026-04-05: dungeoncrawler invokeModelDirect/testConnection bypassed buildBedrockClient() and fallback chain after CEO hotfix that correctly updated forseti but not all dungeoncrawler paths)
- [ ] Drupal-specific: `_method: 'POST'` in `requirements:` is NOT enforced by Drupal 11's routing system. Method restriction requires `methods: [POST]` at the route level. Any JSON API POST route using `_method: 'POST'` in requirements is effectively open to GET — verify with `grep -B2 '_method:' *.routing.yml` and confirm the route has `methods: [POST]` at the route level and `_csrf_request_header_mode: TRUE` in requirements. (2026-04-06: ai_conversation.api_send_message on both forseti and dungeoncrawler used _method: POST in requirements — not enforced)
- [ ] Environment path fallbacks — when reviewing code using `getenv('COPILOT_HQ_ROOT') ?: '<fallback>'`, verify the fallback is the production path `/home/ubuntu/forseti.life/copilot-hq` not a dev-machine path like `/home/keithaumiller/...`. If fallback is wrong, dashboard/telemetry silently fails when the env var is unset. (2026-04-06: DashboardController.php langgraphPath() introduced wrong fallback to /home/keithaumiller/copilot-sessions-hq)

## KB reference requirement
- Before reviewing, search `knowledgebase/` for relevant prior reviews/lessons.
- In findings output, include at least one KB reference or explicitly state "none found".

## Escalation
- Follow `org-chart/org-wide.instructions.md`.
- If blocked on missing context (repo path/URL/creds), escalate to your Supervisor with a concrete request and ROI.

## Supervisor
- Supervisor: `ceo-copilot`
