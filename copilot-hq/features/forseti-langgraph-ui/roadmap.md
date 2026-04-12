# LangGraph UI — Release Roadmap

- Product: forseti.life
- Module: copilot_agent_tracker
- Feature project: forseti-langgraph-ui
- Author: architect-copilot
- Date: 2026-04-08
- Status: active

---

## Release Cycle Overview

| Release | Status | Theme | Scope |
|---|---|---|---|
| 20260405-forseti-release-c | **shipped** | LangGraph Telemetry Foundation | Telemetry schema fix, dashboard path fix, feature progress auto-refresh |
| 20260405-forseti-release-c (Phase 1b) | **shipped** | Console Stubs Scaffold | 7-section console route structure + LangGraphConsoleStubController |
| 20260408-forseti-release-d (Phase 1c) | **shipped** | LangGraph UI Context Enrichment | Workflow Registry, context banners, key terms glossaries on all 15 pages; Live/Stub status per console subsection |
| 20260408-forseti-release-d | **shipped** | Agent Tracker Core + Schema | copilot_agent_tracker DB + telemetry API + admin dashboard UI |
| 20260411-coordinated-release | **shipped** | Console Build + Test Sections | State schema visualization, node topology, test/eval scorecards (ahead of schedule) |
| 20260411-forseti-release-b | **shipped** | Release Control Panel (read-only) | Release panel wired to release state files and PM signoff data |
| 20260412-forseti-release-e | **in-flight** | Console Wiring — Run + Session | Wire Run/Session panels to live orchestrator tick data |
| forseti-release-f | **planned** | Console Wiring — Observe + Feature Progress | Node traces, metrics, drift detection wired to tick stream |
| forseti-release-h | **planned** | Release Control Panel (mutations) | Graph version management, promotion flow, canary controls (with strong auth gates) |

---

## Shipped

### forseti-release-c — LangGraph Telemetry Foundation
**Theme:** Restore CEO dashboard visibility; fix telemetry schema mismatch

**Delivered:**
| Item | Commit | Notes |
|---|---|---|
| `DashboardController.php` — `langgraphPath()` helper | `62b95688` | Replaced all 10 hardcoded path constant usages with `COPILOT_HQ_ROOT`-aware helper |
| `engine.py` telemetry schema fix | `7fadeb4a` | Now writes `step_results{}`, `parity_ok`, `provider`, `dry_run` — matches dashboard expectations |
| `generate-feature-progress.py` auto-refresh | `3c134210` | Regenerates `FEATURE_PROGRESS.md` from all 48 feature files on every orchestrator tick |
| `engine_mode` detection fix | `3c134210` | DashboardController reads from tick data (`step_results`/`dry_run`) not log-substring |

**QA verdict:** APPROVE (2026-04-06) — 0 violations, engine_mode = `langgraph`, provider = `ShellProvider`, feature-progress live

---

### forseti-release-c — Console Stubs Phase 1
**Theme:** Scaffold the full LangGraph management console with clean architecture primitives

**Delivered:**
| Item | Notes |
|---|---|
| `LangGraphConsoleStubController.php` | New controller with `sectionMap()` defining 7 sections × 4-5 subsections |
| 7 console routes wired | `/langgraph-console`, `/build`, `/test`, `/run`, `/observe`, `/release`, `/config` |
| Section definitions | home (graph contract, runtime objects, durability model, control gates); build (state schema, nodes/routing, subgraphs, tool calling, prompts); test (path scenarios, checkpoint replay, eval scorecards, safety gates); run (threads/runs, stream events, resume/retry, concurrency); observe (node traces, runtime metrics, drift/anomalies, alerts); release (graph versions, promotion flow, canary controls); config |

**QA verdict:** APPROVE (2026-04-06) — all console routes return 403 for anonymous (correct), no PHP errors

---

### forseti-release-d (Phase 1c) — LangGraph UI Context Enrichment
**Theme:** Eliminate all ambiguity on LangGraph management pages; add Workflow Registry and Live/Stub indicators

**Delivered:**
| Item | Commits | Notes |
|---|---|---|
| Workflow Registry on Dashboard home | `848a38621` | Groups workflows by Scope (System/Site) with status and console links |
| `renderLangGraphContextBanner()` + `renderLangGraphKeyTerms()` helpers | committed | DashboardController — blue info block + collapsible `<details>` glossary panel |
| `renderConsoleContext()` + `renderKeyTerms()` helpers | committed | LangGraphConsoleStubController — same pattern |
| Context banners + key terms: all 8 Dashboard LangGraph pages | `a630b6171` | Dashboard home, Workflow Mgmt, Session, Feature Flow, Parity, Release Status, Release Evidence, Release Triage |
| Context banners + key terms: all 7 Console pages | committed | home, run, observe, build, test, release, admin |
| Live/Stub status per subsection in section nav tables | `5ead323e8` | `buildSectionRows()` checks 20 wired keys; green 🟢 Live or grey ⬜ Stub |

---

## In-Flight

### forseti-release-d — Agent Tracker Core (`forseti-copilot-agent-tracker`)
**Status:** shipped (release 20260412-forseti-release-d)
**Priority:** P1
**Theme:** Deliver the actual agent tracking DB and telemetry API that underpins the dashboard

| Item | Status | Notes |
|---|---|---|
| Telemetry POST endpoint `/api/copilot-agent-tracker/event` | shipped | Token-auth, input validation, CSRF-exempt API route |
| `copilot_agent_tracker_agents` DB table | shipped | Upsert on POST; agent_id, status, current_action, last_seen |
| `copilot_agent_tracker_events` DB table | shipped | Append-only event log; PII-free; no raw chat content |
| Admin agent list view | shipped | AC-HP-01: list with id, last status, last action, last-seen |
| Admin agent detail view | shipped | AC-HP-02: sanitized event stream |
| Permission: `administer copilot agent tracker` | shipped | All admin routes require this; anonymous → 302 to login |
| CSRF guards on state-changing endpoints | shipped | POST endpoints require token or token-auth header |

**Feature file:** `features/forseti-copilot-agent-tracker/feature.md` — Status: shipped

---

### forseti-langgraph-console-build-sections + forseti-langgraph-console-test-sections
**Status:** shipped (20260411-coordinated-release — ahead of roadmap schedule)
**Feature files:** `features/forseti-langgraph-console-build-sections/feature.md`, `features/forseti-langgraph-console-test-sections/feature.md`

---

### forseti-langgraph-console-release-panel
**Status:** shipped (20260411-forseti-release-b — read-only release observability panel)
**Feature file:** `features/forseti-langgraph-console-release-panel/feature.md`

---

## In-Flight (forseti-release-e active)

### forseti-release-e — Console Wiring: Run + Session Panels
**Status:** in-flight (release `20260412-forseti-release-e` started 2026-04-12)
**Theme:** Replace stub placeholders in Run and Session sections with live data from the orchestrator tick stream

**Feature:** `features/forseti-langgraph-console-run-session/feature.md`

**Scope:**
| Console Section | Subsection | Live Data Source |
|---|---|---|
| Run | Threads & Runs | `langgraph-ticks.jsonl` — active run IDs, thread IDs, status |
| Run | Stream Events | tick `step_results` — streaming event timeline |
| Run | Resume & Retry | Orchestrator state: `needs-info`/`blocked` agent items |
| Run | Concurrency | tick `selected_agents`, worker count from `pick_agents` step |
| Session Health | (current stub) | tick `parity_ok`, `provider`, last tick timestamp |

**Deps:** forseti-release-d shipped (agent tracker tables exist)
**Key risk:** Read-only access to `langgraph-ticks.jsonl` from PHP — confirm `COPILOT_HQ_ROOT` env available in web context

---

## Planned

---

### forseti-release-f — Console Wiring: Observe + Feature Progress
**Theme:** Wire the Observe section and Feature Progress panel to live tick stream and feature file data

**Scope (proposed):**
| Console Section | Subsection | Live Data Source |
|---|---|---|
| Observe | Node Traces | tick `step_results` — per-node path and decision trace |
| Observe | Runtime Metrics | tick latency, token counts (if emitted), failure counts |
| Observe | Drift & Anomalies | compare tick step_results distribution to baseline |
| Observe | Alerts & Incidents | correlate executor-failures from `tmp/executor-failures/` |
| Feature Progress | Full page | Already live at `/langgraph/feature-progress` — integrate into console frame |

---

### forseti-release-g — Console Build + Test Sections
**Theme:** Provide design-time visibility into graph topology and test/eval scorecard framework

**Scope (proposed):**
| Console Section | Subsection | Notes |
|---|---|---|
| Build | State Schema | Read `engine.py` `LangGraphDeps` TypedDict and render as schema table |
| Build | Nodes & Routing | Parse `runtime_graph/engine.py` node/edge definitions; render as topology |
| Build | Subgraphs | Visualize subgraph boundaries if present |
| Build | Tool Calling | List tool invocations registered in engine |
| Test | Path Scenarios | Golden-path test case registry (new — define format) |
| Test | Eval Scorecards | Task success/hallucination/tool-accuracy placeholders (link to agent_evaluation module) |

**Deps:** `agent_evaluation` module (currently in-progress), `local-llm-integration` feature

---

### forseti-release-h — Release Control Panel
**Theme:** Promote LangGraph console from read-only to control-plane; add graph version management and canary controls

**Scope (proposed):**
| Console Section | Subsection | Notes |
|---|---|---|
| Release | Graph Versions | Graph artifact/version inventory (new — needs versioning scheme) |
| Release | Promotion Flow | Dev → staging → prod gate UI (tied to release cycle state in `tmp/release-cycle-active/`) |
| Release | Canary Controls | Traffic-split controls; requires orchestrator canary support |

**Security gate (required before scoping):** All state-changing release controls must have:
- `administer copilot agent tracker` permission check
- CSRF token validation
- Audit log entry on every control action

**Board decision required before Release H scope commit:** Release control panel grants UI access to alter org-wide orchestration behavior. Confirm scope, auth model, and rollback strategy with Keith before dev begins.

---

## Deferred / Out of Scope Until Explicitly Scoped

| Item | Reason |
|---|---|
| LangGraph Cloud integration | Using local orchestrator; no cloud deployment yet |
| Multi-graph / multi-project support | Single orchestrator instance; revisit when org scales |
| Public-facing graph status page | Admin-only for now; consider after org launch |
| Real-time WebSocket streaming | AJAX polling sufficient for current scale |

---

## Key Files Reference

| File | Purpose |
|---|---|
| `web/modules/custom/copilot_agent_tracker/src/Controller/DashboardController.php` | Telemetry dashboard (4,636 lines) |
| `web/modules/custom/copilot_agent_tracker/src/Controller/LangGraphConsoleStubController.php` | Console stubs — primary buildout target |
| `web/modules/custom/copilot_agent_tracker/copilot_agent_tracker.routing.yml` | All routes (telemetry + console) |
| `copilot-hq/orchestrator/runtime_graph/engine.py` | LangGraph graph definition |
| `copilot-hq/orchestrator/run.py` | Tick pipeline entry point |
| `copilot-hq/inbox/responses/langgraph-ticks.jsonl` | Live tick telemetry |
| `copilot-hq/inbox/responses/langgraph-parity-latest.json` | Live parity snapshot |
| `copilot-hq/dashboards/FEATURE_PROGRESS.md` | Feature flow (auto-refreshed) |
| `features/forseti-copilot-agent-tracker/01-acceptance-criteria.md` | Agent tracker AC (30 rows) |
| `features/forseti-copilot-agent-tracker/03-test-plan.md` | Agent tracker test plan (30 rows) |
