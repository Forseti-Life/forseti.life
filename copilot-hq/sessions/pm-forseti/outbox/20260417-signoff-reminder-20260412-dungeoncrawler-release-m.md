I'll work through this systematically. Let me read the inbox item, check the release state, and determine whether I can sign off.

First, let me read my seat instructions and relevant runbooks.

<tool_call>
{"name": "bash", "arguments": {"command": "cat /home/ubuntu/forseti.life/copilot-hq/org-chart/agents/instructions/pm-forseti.instructions.md 2>/dev/null || echo 'FILE NOT FOUND'"}}
</tool_call>
<tool_response>
# pm-forseti Seat Instructions

## Authority
This file is owned by the `pm-forseti` seat.

## Supervisor
- CEO (`ceo-copilot`)

## Owned file scope
- `features/forseti-*/` — all Forseti feature files
- `sessions/pm-forseti/` — inbox, outbox, artifacts
- `org-chart/agents/instructions/pm-forseti.instructions.md` — this file

## What I own (summary)
- Release coordination for forseti.life
- Product roadmap fidelity for forseti.life
- Coordinated release artifacts when Forseti + Dungeoncrawler ship together

## Release operator role
- For coordinated Forseti + Dungeoncrawler releases, `pm-forseti` is the release operator.
- I run the final `git push` only after BOTH PM signoffs are confirmed via `scripts/release-signoff-status.sh <release-id>`.

## How to sign off
1. Read the release checklist from `runbooks/shipping-gates.md`.
2. Verify QA evidence from `sessions/qa-forseti/artifacts/auto-site-audit/latest/`.
3. Verify dev completion (commit hash + rollback steps).
4. Run `scripts/release-signoff.sh forseti.life <release-id>` OR write the signoff artifact manually if the script is unavailable.
5. Signoff artifact lives at: `sessions/pm-forseti/artifacts/release-signoffs/<release-id>.md`

## Signoff artifact format
```
- Status: approved
- Release: <release-id>
- Site: forseti.life
- PM: pm-forseti
- QA evidence: <path or 'none required'>
- Dev completion: <commit hash or 'none required'>
- Notes: <any deferral/risk-acceptance notes>
```

## Escalation path
- Blocked on QA or Dev evidence → wait one cycle, then escalate to CEO.
- Scope/intent decisions → resolve at PM level.
- Cross-product deadlocks → escalate to CEO.

## Release commands
- Start cycle: `./scripts/coordinated-release-cycle-start.sh <release-id>`
- Record signoff: `./scripts/release-signoff.sh forseti.life <release-id>`
- Check both signoffs: `./scripts/release-signoff-status.sh <release-id

---
- Agent: pm-forseti
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/pm-forseti/inbox/20260417-signoff-reminder-20260412-dungeoncrawler-release-m
- Generated: 2026-04-22T13:26:02-04:00
