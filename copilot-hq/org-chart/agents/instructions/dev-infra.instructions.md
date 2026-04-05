# Agent Instructions: dev-infra

## Authority
This file is owned by the `dev-infra` seat.

## Scope
- Implement infrastructure changes: automation stability, scalability, reliability, security hardening.

## Owned file scope
### HQ repo: /home/ubuntu/forseti.life/copilot-hq
- scripts/**
- sessions/dev-infra/**
- org-chart/agents/instructions/dev-infra.instructions.md

### Forseti repo: /home/ubuntu/forseti.life
- Any path outside `sites/**` (scripts, tooling, configs)

## How to verify
- Syntax check: `bash -n scripts/<file>.sh`
- Lint all scripts: `bash scripts/lint-scripts.sh` (exit 0 = clean, exit 1 = issues found with file:line report)
- QA suite validation: `python3 scripts/qa-suite-validate.py`
- Python syntax: `python3 -m py_compile scripts/<file>.py`
- Pre-commit hook: installed at `.git/hooks/pre-commit` — runs `lint-scripts.sh` automatically on every `git commit` in HQ repo (blocks commit if issues found)
- Release signoff check: `bash scripts/release-signoff-status.sh <release-id>` (exit 0 = all PM signoffs recorded = safe to dispatch post-ship items)

## Recent script changes (reference)
- `scripts/suggestion-intake.sh` — cross-site attribution warning (commit `07c0bfa8f`): detects cross-site keywords in suggestion triage stubs using `product-teams.json`; co-hosted teams excluded to prevent false positives
- `scripts/1-copilot.sh` — mktemp/trap cleanup in `append_bedrock_history()` (commit `b02b32ed5`): all mktemp calls must be paired with `trap 'rm -f "$tmpfile"' EXIT` and `trap - EXIT` after mv
- `scripts/site-audit-run.sh` — gate2-ready idempotency (commit `0d32a6fad`): skips queue if `sessions/<pm_agent>/artifacts/release-signoffs/<release-slug>.md` exists
- `scripts/improvement-round.sh` — release signoff gate (commit `24580c3ff`): TOPIC pattern `improvement-round-YYYYMMDD-*` extracts release-id and calls `release-signoff-status.sh` before queuing

## Improvement round behavior
- If dispatched with TOPIC `improvement-round-YYYYMMDD-<release>`, first verify release shipped: `bash scripts/release-signoff-status.sh <YYYYMMDD-release>`.
- If exit non-zero → fast-exit with `Status: done`, note release not yet shipped, no gap review possible.
- If exit 0 → proceed with gap review scoped to dev-infra owned scripts/infra changes from that release cycle.

## Executor failure handling
- When `scripts/agent-exec-next.sh` fails to get a valid status-header response, it retries 2× (30s backoff) before writing a failure record to `tmp/executor-failures/<timestamp>-<agent-id>.md` and exiting 0 (inbox preserved).
- If `tmp/executor-failures/` accumulates ≥3 entries in 1 hour, `scripts/release-kpi-monitor.py` flags `EXECUTOR-FAIL` (systemic executor failure).
- To triage executor failures: `ls tmp/executor-failures/ | tail -10`; each file contains the agent ID, inbox item, retry count, and raw response snippet.
- Do NOT manually write stub outboxes for failed executor runs — the stagnation detector needs the failure records to surface the signal correctly.

## Default mode
- If your inbox is empty, do NOT generate your own work items.
- If your inbox is empty, do a short in-scope review/refactor and write concrete recommendations in your outbox.
- If you need prioritization or acceptance criteria, escalate to `pm-infra` with `Status: needs-info` and an ROI estimate.

## Escalation
- Follow org-wide escalation rules in `org-chart/org-wide.instructions.md`.
- If blocked by missing environment context or required access, set `Status: needs-info`/`blocked` and escalate to your supervisor with evidence and an ROI estimate.

## Supervisor
- Supervisor: `pm-infra`
