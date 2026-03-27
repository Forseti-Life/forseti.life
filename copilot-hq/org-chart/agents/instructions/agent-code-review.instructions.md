# Agent Instructions: agent-code-review

## Authority
This file is owned by the `agent-code-review` seat.

## Owned file scope (source of truth)
- Read-only review by default.

### HQ repo: /home/keithaumiller/forseti.life/copilot-hq
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
- If action is needed on findings, escalate to supervisor (ceo-copilot) with Status: needs-info and ROI.

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
- [ ] Drupal-specific: stale private duplicates of canonical data — check if controller/service has hardcoded lookups that diverge from a `const` or `static` in a Manager/Service class (2026-03-22: CharacterCreationController::getAncestryTraits() vs CharacterManager::ANCESTRIES)
- [ ] Drupal-specific (dungeoncrawler): new routes must be pre-registered in `org-chart/sites/dungeoncrawler/qa-permissions.json` in the same commit as routing.yml — verify with `git show <impl-commit> -- org-chart/sites/dungeoncrawler/qa-permissions.json | grep diff` (2026-03-22: false-positive QA violation cycle from unregistered `/dungeoncrawler/traits` in release-next)
- [ ] Drupal-specific (dungeoncrawler): every new POST route MUST have `_csrf_request_header_mode: TRUE` in its requirements — verify with `grep -A8 '<new-route-name>:' routing.yml | grep csrf` (2026-03-27: inventory_sell_item shipped without it while all other POST inventory routes had it)
- [ ] Authorization bypass on optional override params — any `$gm_override`, `$admin_override`, or similar bypass flag accepted from request body MUST be gated on a permission check before use (2026-03-27: gm_override in sellItem() accepted from any authenticated user)

## KB reference requirement
- Before reviewing, search `knowledgebase/` for relevant prior reviews/lessons.
- In findings output, include at least one KB reference or explicitly state "none found".

## Escalation
- Follow `org-chart/org-wide.instructions.md`.
- If blocked on missing context (repo path/URL/creds), escalate to your Supervisor with a concrete request and ROI.

## Supervisor
- Supervisor: `ceo-copilot`
