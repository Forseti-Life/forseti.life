# Open CSRF Findings Registry

**Maintained by:** sec-analyst-infra (ARGUS)
**Last updated:** 2026-03-19
**Spot-check 2026-03-19:** FINDING-1 CLOSED (confirmed by code inspection). FINDING-2 (ai_conversation + agent_evaluation MISPLACED) STILL OPEN across forseti and dungeoncrawler — patches were provided but MISPLACED-type was not in scope of the patch-mode execution (which targeted MISSING, not MISPLACED).
**Source of truth:** run `bash sessions/sec-analyst-infra/artifacts/csrf-scan-tool/csrf-route-scan.sh <repo_root>` to regenerate current status.

---

## Summary

| Finding ID | Site | Module | Route | Type | Status |
|---|---|---|---|---|---|
| FINDING-1a | forseti | `job_hunter` | credentials_delete | MISSING | **CLOSED** 2026-03-01 |
| FINDING-1b | forseti | `job_hunter` | credentials_test | MISSING | **CLOSED** 2026-03-01 |
| FINDING-2a | forseti | `ai_conversation` | ai_conversation.send_message | MISPLACED (options:) | **OPEN** — patches written |
| FINDING-2b | dungeoncrawler | `ai_conversation` | ai_conversation.send_message | MISPLACED (options:) | **OPEN** — patches written |
| FINDING-2c | forseti | `agent_evaluation` | agent_evaluation.send_message | MISPLACED (options:) | **OPEN** — patches written |

---

## FINDING-1 (CLOSED)

**Description:** `job_hunter.credentials_delete` and `job_hunter.credentials_test` POST routes were missing `_csrf_token: 'TRUE'` in `requirements:`.

**Fix:** Applied via CSRF patch-mode execution (dev-infra commits `74a4a6633` forseti + `603223bb4` dungeoncrawler, QA PASS `0e415d34`).

**Verified:** 2026-03-19 — `grep -n -A10 "credentials_delete" job_hunter.routing.yml` confirms `_csrf_token: 'TRUE'` at line 1107 under `requirements:`.

---

## FINDING-2 (OPEN — escalated to pm-infra)

**Description:** `ai_conversation.send_message` and `agent_evaluation.send_message` have `_csrf_token: TRUE` under `options:` instead of `requirements:`. Drupal's access checker does not read `options:` — this is a silent no-op.

**Impact:** LLM chat endpoints and agent evaluation endpoint are unprotected against CSRF. Attacker can force logged-in user to submit arbitrary AI prompts (conversation injection, API credit consumption).

**Severity:** Medium (original classification). Elevated concern: LLM endpoint abuse (API credit drain) adds financial dimension beyond typical CSRF.

### FINDING-2a — forseti ai_conversation.send_message

**File:** `/home/keithaumiller/forseti.life/sites/forseti/web/modules/custom/ai_conversation/ai_conversation.routing.yml`
**Line:** 107 (route start), 115 (`_csrf_token` under `options:`)

**Ready-to-apply patch:**
```yaml
ai_conversation.send_message:
  path: '/ai-conversation/send-message'
  defaults:
    _controller: '\Drupal\ai_conversation\Controller\ChatController::sendMessage'
  methods: [POST]
  requirements:
    _permission: 'use ai conversation'
    _csrf_token: 'TRUE'
```
Remove `_method: 'POST'` from `requirements:` and remove `options:` block entirely.

### FINDING-2b — dungeoncrawler ai_conversation.send_message

**File:** `/home/keithaumiller/forseti.life/sites/dungeoncrawler/web/modules/custom/ai_conversation/ai_conversation.routing.yml`
**Line:** 99 (route start), 107 (`_csrf_token` under `options:`)

Same patch as FINDING-2a.

### FINDING-2c — forseti agent_evaluation.send_message

**File:** `/home/keithaumiller/forseti.life/sites/forseti/web/modules/custom/agent_evaluation/agent_evaluation.routing.yml`
**Line:** 58 (route start), 65-66 (`_csrf_token` under `options:`)

**Ready-to-apply patch:**
```yaml
agent_evaluation.send_message:
  path: '/agent-evaluation/send-message'
  methods: [POST]
  requirements:
    _permission: 'use ai conversation'
    _csrf_token: 'TRUE'
```
Remove `options:` block entirely.

### Verification command (all three)

```bash
bash sessions/sec-analyst-infra/artifacts/csrf-scan-tool/csrf-route-scan.sh /home/keithaumiller/forseti.life
# Expected: exit 0, zero MISPLACED or MISSING flags for ai_conversation / agent_evaluation
```

---

## Status legend
- **OPEN**: Finding identified; patch written; not confirmed applied
- **CLOSED**: Fix confirmed by direct code inspection or QA approval
