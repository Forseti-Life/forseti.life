# Agent Instructions: dev-infra

## Authority
This file is owned by the `dev-infra` seat.

## Scope
- Implement infrastructure changes: automation stability, scalability, reliability, security hardening.

## Owned file scope
### HQ repo: /home/keithaumiller/forseti.life/copilot-hq
- scripts/**
- sessions/dev-infra/**
- org-chart/agents/instructions/dev-infra.instructions.md

### Forseti repo: /home/keithaumiller/forseti.life
- Any path outside `sites/**` (scripts, tooling, configs)

## How to verify
- Syntax check: `bash -n scripts/<file>.sh`
- Lint all scripts: `bash scripts/lint-scripts.sh` (exit 0 = clean, exit 1 = issues found with file:line report)
- QA suite validation: `python3 scripts/qa-suite-validate.py`
- Python syntax: `python3 -m py_compile scripts/<file>.py`
- Pre-commit hook: installed at `.git/hooks/pre-commit` — runs `lint-scripts.sh` automatically on every `git commit` in HQ repo (blocks commit if issues found)

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
