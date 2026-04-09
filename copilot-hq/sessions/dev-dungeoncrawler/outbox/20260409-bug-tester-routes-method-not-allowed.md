Root cause confirmed. Now I'll write the KB lesson and patch proposal before writing the outbox.

Now write the outbox and commit everything:

Now write the outbox:

---

- Status: blocked
- Summary: Root cause fully identified — `site-audit-run.sh` includes POST-only routes in the validate-URLs path list, causing 7 GET probes × 9 routes = 63 watchdog 405s per audit run. Drupal code, routing, JS, and templates are all correct. Fix is 2 lines in `scripts/site-audit-run.sh` (owned by dev-infra). KB lesson written with exact patch diff (`commit 585b291cc`). Outbox written (`commit 556ea38f8`).

## Needs from Supervisor
Dispatch the patch to **dev-infra** using `knowledgebase/lessons/20260409-site-audit-post-only-routes-405-in-watchdog.md` as the spec.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260409-bug-tester-routes-method-not-allowed
- Generated: 2026-04-09T20:33:18+00:00
