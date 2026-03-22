# Copilot Sessions HQ

This repository is the canonical control plane for HQ agent execution, release-cycle orchestration, and session tracking across Copilot CLI, local LLMs, and Bedrock-backed assistant paths.

## Principles
- **Local sessions are not automatically committed here.** Only add/export what you intend to persist.
- **No secrets.** Sanitize exports before committing.

## Layout
- `org-chart/` — organizational model (CEO → departments → delegated sessions)
- `sessions/` — per-session folders (exports, summaries, artifacts)
- `runbooks/` — how we run/close sessions consistently
- `templates/` — standard templates for session summaries and handoffs
- `scripts/` — helper scripts for exporting/sanitizing (optional)

## Orchestration
See `runbooks/orchestration.md` for the current end-to-end process flow (LangGraph orchestrator + systemd runtime + publishing).

## Technology stack
See `runbooks/technology-stack.md` for a full map of the agentic system stack (queues, executors, local LLM layer, publishing, control plane, and observability).

## Documentation index
### Process flows
- `runbooks/release-cycle-process-flow.md` — release-cycle stages and progression rules.
- `runbooks/product-team-onboarding.md` — standard onboarding process for adding new product teams.
- `runbooks/orchestration.md` — end-to-end orchestration process.
- `runbooks/session-lifecycle.md` — how sessions are started, managed, and closed.
- `runbooks/session-monitoring.md` — session health and monitoring workflow.
- `runbooks/coordinated-release.md` — coordinated release execution model.

### Dashboards
- `dashboards/FEATURE_PROGRESS.md` — feature progress dashboard definitions and usage.
- `dashboards/SESSION_MONITORING.md` — session monitoring dashboard definitions and usage.

## Monitoring + control path
The org automation control path is deterministic at the control layer and agentic at the troubleshooting layer.

### 1) Source of truth: org enable/disable
- State toggle: `scripts/org-control.sh`
- State read gate: `scripts/is-org-enabled.sh`
- State file default: `/var/tmp/copilot-sessions-hq/org-control.json` (legacy fallback supported)

### 2) Process convergence (start/stop loops)
- Converger: `scripts/hq-automation.sh converge`
- Behavior:
	- enabled=true → starts required loops
	- enabled=false → stops required loops

### 3) Watchdog enforcement
- Watchdog runner: `scripts/hq-automation-watchdog.sh`
- Installed by: `scripts/install-cron-hq-automation.sh`
- Cadence:
	- `@reboot` converge
	- every minute watchdog converge

### 4) Runtime loops and cadence
- `scripts/orchestrator-loop.sh` — every 60s (primary LangGraph execution loop)
- `scripts/publish-forseti-agent-tracker-loop.sh` — every 60s (dashboard telemetry publish)
- `scripts/auto-checkpoint-loop.sh` — every 7200s
- `scripts/site-audit-loop.sh` — every 300s (optional, only when enabled)
- `scripts/hq-automation-watchdog.sh` — every minute via cron (convergence + suggestion intake)

Legacy loops (`ceo-inbox-loop`, `inbox-loop`, `ceo-health-loop`, `2-ceo-opsloop`) are not part of the default production runtime and should remain stopped.

## Production setup essentials
- Deploy and start runtime using `.github/workflows/deploy.yml` (branch: `master`).
- Production deploy uses a full repository checkout at `$HOME/forseti.life` by default (override with `REPO_DEPLOY_DIR`), and runs HQ from `$REPO_DEPLOY_DIR/copilot-hq` (override with `HQ_DEPLOY_DIR`).
- Deploy workflow behavior is idempotent: first deploy runs `scripts/setup.sh`; existing deploys run `scripts/verify-hq-runtime.sh --strict` and auto-run `scripts/setup.sh` only if verification fails.
- Run `./scripts/verify-hq-runtime.sh --strict` after deploy.
- Select agentic backend via `HQ_AGENTIC_BACKEND`:
	- `auto` (default): prefer Copilot CLI; fallback to Bedrock assistant script
	- `copilot`: require Copilot CLI
	- `bedrock`: require Bedrock assistant script
- Ensure `scripts/bedrock-assist.sh` is executable if using Bedrock path.
- Validate org state with `./scripts/org-control.sh status --one-line` and runtime state with `./scripts/hq-automation.sh status`.

## How incidents are handled
### Deterministic control-plane recovery
- If loops drift from desired state, watchdog runs converge and repairs start/stop state.
- If org is disabled, loops either stop or skip work at each cycle gate.

### Agentic troubleshooting path
- For stalls/uncertain states, health checks and queue loops generate actionable inbox work items.
- Orchestrator/executor then runs agent seats to investigate, diagnose, and propose or apply fixes.
- In short: control-plane recovery is rule-based; root-cause troubleshooting is handled by agentic execution.

## Operations quick-check
- Snapshot: `./scripts/hq-status.sh`
- Org state: `./scripts/org-control.sh status --one-line`
- Force converge: `./scripts/hq-automation.sh converge`
- Watchdog log: `inbox/responses/hq-automation-watchdog.log`

### Production research scripts
- `./scripts/prod-assumptions-audit.sh [hq_dir]` — host context, runtime assumptions, release-cycle files, cron, and recent logs.
- `./scripts/prod-writeability-check.sh [hq_dir]` — write-permission checks for HQ runtime directories (current user + optional `www-data`).
- `./scripts/prod-release-cycle-flow-check.sh [hq_dir]` — validates release-cycle paths/state and runs one dry release-cycle step.

## Preparing for public release
Start with `PUBLIC_REPO_PREP.md` for a staged publication checklist (security scrub, history review, docs/legal, and release steps).

Public metadata and policies:
- `LICENSE`
- `CODE_OF_CONDUCT.md`
- `CONTRIBUTING.md`
- `SECURITY.md`

Current release-prep artifacts:
- `runbooks/publication-readiness-20260308.md`
- `runbooks/history-secret-scan-20260308.txt`
- `runbooks/public-release-gate-20260308.md`

For a clear public-facing explanation of value proposition, purpose, and platform boundaries with `forseti.life`, see:
- `runbooks/public-repo-positioning.md`

For a practical private/public split, see:
- `runbooks/private-public-dual-repo.md`

Automation scripts:
- `scripts/setup-public-mirror.sh` (one-time mirror setup)
- `scripts/export-public-mirror.sh` (repeatable private -> public sync)
