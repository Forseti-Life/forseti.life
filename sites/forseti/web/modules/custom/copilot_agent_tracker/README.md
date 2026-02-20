# Copilot Agent Tracker

Admin module to track Copilot agent status and a sanitized event stream (no raw chat transcripts).

## Routes
- `/admin/reports/copilot-agent-tracker` (admin dashboard)
- `/admin/reports/copilot-agent-tracker/agent/{agent_id}` (agent detail)
- `POST /api/copilot-agent-tracker/event` (internal telemetry; permission-gated)

## Data model
- `copilot_agent_tracker_agents`: one row per agent, updated via merge/upsert
- `copilot_agent_tracker_events`: append-only event stream

