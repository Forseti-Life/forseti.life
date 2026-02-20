# Copilot Agent Tracker

Admin module to track Copilot agent status and a sanitized event stream (no raw chat transcripts).

## Routes
- `/admin/reports/copilot-agent-tracker` (admin dashboard)
- `/admin/reports/copilot-agent-tracker/agent/{agent_id}` (agent detail)
- `POST /api/copilot-agent-tracker/event` (internal telemetry; header token required)

## Telemetry auth
Telemetry POSTs must include the header:
- `X-Copilot-Agent-Tracker-Token: <value>`

The token is generated on module install and stored in Drupal state (not in git).
Admins can view the token on the dashboard page.

## Data model
- `copilot_agent_tracker_agents`: one row per agent, updated via merge/upsert
- `copilot_agent_tracker_events`: append-only event stream
