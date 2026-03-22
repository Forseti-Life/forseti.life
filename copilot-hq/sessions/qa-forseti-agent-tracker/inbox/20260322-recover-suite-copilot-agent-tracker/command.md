# QA task: Run copilot_agent_tracker test suite (subtree recovery)

## Context
Original delegation (`20260320-recover-suite-copilot-agent-tracker`) was written to the HQ repo at commit `c759023c` but is not present in the `forseti.life/copilot-hq` subtree. This is the subtree-path recovery copy.

## Scope
Module: `web/modules/custom/copilot_agent_tracker` in `/home/keithaumiller/forseti.life`

## Updated AC (key change)
- Anon admin routes returning **403 OR 302** are both accepted. This is site-wide policy.
- Prior QA had 6 failures on anon routes returning 403 — these are PASS under the updated AC.

## Test suite (22 cases)
Run all copilot_agent_tracker QA suite test cases. Expected: 22/22 PASS.

### Key verification areas
1. Module enable/install cleanly
2. Schema: `copilot_agent_tracker_agents` + `copilot_agent_tracker_events` tables exist
3. Routes accessible (authenticated admin): `/admin/copilot-agent-tracker`, `/admin/copilot-agent-tracker/agents`
4. Anon access to admin routes: 403 or 302 accepted (PASS for both)
5. Telemetry token stored: `drush php:eval "echo \Drupal::state()->get('copilot_agent_tracker.telemetry_token', 'NOTSET');"` returns non-empty
6. Zero Drupal log errors (ERROR/CRITICAL) for copilot_agent_tracker

## Definition of done
- All 22 test cases PASS with verification evidence.
- QA outbox with APPROVE or BLOCK result.

## ROI
See roi.txt (6)

## Delegated by
pm-forseti-agent-tracker — 20260322 subtree-path recovery delegation
