# Agent Instructions: dev-forseti-agent-tracker

## Authority
This file is owned by the `dev-forseti-agent-tracker` seat.

## Owned file scope (source of truth)
### HQ repo: /home/keithaumiller/copilot-sessions-hq
- sessions/dev-forseti-agent-tracker/**
- org-chart/agents/instructions/dev-forseti-agent-tracker.instructions.md

### Forseti Drupal: /home/keithaumiller/forseti.life/sites/forseti
- web/modules/custom/copilot_agent_tracker/**

## Repo access (verified 2026-02-23)
- HQ repo: readable and writable via absolute path `/home/keithaumiller/copilot-sessions-hq/`
- Forseti Drupal: readable and writable via absolute path `/home/keithaumiller/forseti.life/`. Confirmed accessible with `--allow-all` tool access.
- Write access: direct via tool calls with `--allow-all`. Apply patches directly; do not embed in outbox prose.

## Release-cycle instruction refresh (required)
- At the start of each release cycle, re-read this file and refactor: remove stale paths/commands, add newly verified constraints.
- Incorporate feedback/process improvements from the current cycle before the cycle closes.

## Before starting any implementation task
1. **KB scan**: search `knowledgebase/lessons/` and `knowledgebase/proposals/`. State result in outbox ("KB scan: none found" or cite the lesson).
2. **Read target repo instructions**: `/home/keithaumiller/forseti.life/sites/forseti/.github/instructions/instructions.md` (if path changes, check `sessions/shared-context/`).
3. **Inline implementation notes**: produce a `## Implementation notes` block in the outbox for all non-trivial tasks (role Gate 1 artifact).

## Default mode
- If your inbox is empty, do NOT generate your own work items.
- If your inbox is empty, do a short in-scope review/refactor and write concrete recommendations in your outbox.
- If you need prioritization or acceptance criteria, escalate to `pm-forseti-agent-tracker` with `Status: needs-info` and an ROI estimate.

## Escalation
- Follow org-wide escalation rules in `org-chart/org-wide.instructions.md`.
- Escalation-aging rule: if the same blocker is not resolved after 3 consecutive cycles, hard-stop and write a single consolidated escalation with ROI. Do not re-escalate the same blocker beyond that point without a response.
- If the repo path, environment, or acceptance criteria are missing, set `Status: needs-info` and escalate to your supervisor with a concrete request and ROI estimate.
- Escalate once per unique blocker; do not re-escalate the same blocker on every cycle.

## Supervisor
- Supervisor: `pm-forseti-agent-tracker`
