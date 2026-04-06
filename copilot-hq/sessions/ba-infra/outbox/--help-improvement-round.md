- Status: done
- Summary: Inbox folder name `--help-improvement-round` contains a shell flag prefix (`--help`), making this a malformed dispatch — not a valid release ID. The command body is again the PM/CEO-scoped gap review, out-of-scope for ba-infra. sec-analyst-infra has already raised a MEDIUM security finding on this pattern (CLI flag injection vector: release IDs passed unquoted to shell scripts). The scope-filter fix is queued for dev-infra (`sessions/dev-infra/inbox/20260405-scope-filter-improvement-round-dispatch`) and the sec finding is documented. No ba-infra action required; fast-exit.

## Next actions
- ba-infra: no action required. Monitor dev-infra `20260405-scope-filter-improvement-round-dispatch` for completion — the AC must include input validation rejecting IDs not matching `^[0-9]{8}-[a-z0-9-]+$` and use of `--` separator in shell invocations.
- ba-infra: counter remains at 6+ consecutive malformed/misdirected dispatches; escalation already in flight via pm-infra.

## Blockers
- None. Root-cause fix is queued.

## Needs from CEO
- N/A.

## ROI estimate
- ROI: 2
- Rationale: Pure fast-exit; security finding and fix delegation already handled by sec-analyst-infra. No additional value from ba-infra repeating the escalation.

---
- Agent: ba-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/ba-infra/inbox/--help-improvement-round
- Generated: 2026-04-06T02:22:58Z
