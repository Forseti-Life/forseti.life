# Automated Testing Process Flow

Canonical process-flow reference for `dungeoncrawler_tester` automation.

## Scope

This document describes:
- Synchronous request/response steps
- Asynchronous subprocesses and their cadence
- Timing constraints and timeouts
- Blocking and pause gates that stop progression

## Components

- `StageAutoEnqueueService` (sensor + lever): periodic enqueue of due stages
- `TesterRunQueueWorker` (processor + lever): executes stage command, records state, opens issues
- Dashboard queue endpoints (sensor + lever): operator-triggered queue run/status/log requests
- Drupal state keys (processor memory): run metadata, stage state, issue linkage

## End-to-End Flow

### 1) Stage scheduling (asynchronous)

Source: `src/Service/StageAutoEnqueueService.php`

1. Scheduler inspects stage definitions and stage state.
2. A stage is eligible only if:
   - stage is active (`active != FALSE`)
   - no open linked issue (`issue_status != open`)
   - run status is not `pending`/`running`
   - interval window elapsed (`intervalSeconds`, default 3600s)
3. Eligible stage command is enqueued in `dungeoncrawler_tester_runs`.
4. Run state is set to `pending` immediately to prevent duplicate enqueue.

Timing:
- Default auto-enqueue cadence gate: `3600s` per stage.

Blocking points:
- Inactive stage blocks enqueue.
- Open issue blocks enqueue.
- Pending/running status blocks enqueue.

### 2) Queue execution (asynchronous worker, blocking per item)

Source: `src/Plugin/QueueWorker/TesterRunQueueWorker.php`

1. Queue worker pulls one item.
2. Metadata validation occurs (`stage_id`, `job_id`, command args).
3. Run state transitions to `running`.
4. Command executes via Symfony Process.
5. Run state updates with `succeeded` or `failed`, duration, output excerpt.

Timing:
- Process timeout: `1800s` hard limit per queue item.

Blocking points:
- Worker thread is blocked while command runs (synchronous inside worker).
- Invalid queue metadata short-circuits processing for that item.

### 3) Failure handling and stage pause (synchronous within worker)

Source: `src/Plugin/QueueWorker/TesterRunQueueWorker.php`

When status is `failed`:
1. Worker attempts issue creation (if not already linked and token/repo available).
2. Worker updates stage state:
   - `active = FALSE`
   - sets failure reason/excerpt
   - stores issue number/status when available

Blocking points:
- Stage becomes paused (`active = FALSE`), preventing future auto-enqueue until manual remediation/reactivation.
- Existing linked issue prevents creation of a second issue for same stage state.

### 4) GitHub issue automation subprocess (mixed sync network calls)

Source: `src/Plugin/QueueWorker/TesterRunQueueWorker.php`

1. POST create issue to GitHub REST API.
2. Attempt Copilot assignment via REST assignee identifiers:
   - `@copilot`, `Copilot`, `copilot`
3. If REST assignment does not attach Copilot, fallback to CLI:
   - `gh issue edit --add-assignee @copilot`

Timing:
- GitHub REST request timeout: `10s` per call.
- CLI fallback timeout: `20s`.

Blocking points:
- Network/API latency blocks worker until timeout/success.
- Assignment failures do not block stage pause, but do affect automation handoff quality.

### 5) Operator control loop (synchronous UI/API requests)

Sources:
- `dungeoncrawler_tester.routing.yml` queue routes
- Queue management controller/forms

1. Operator uses dashboard to run queue/status/log actions.
2. Request/response is synchronous from browser perspective.
3. Underlying queue work remains asynchronous and state-driven.

Blocking points:
- If stage remains inactive or issue-open, scheduler will not auto-enqueue that stage.
- Resume requires explicit remediation flow (fix + rerun + state progression).

## State Model and Gates

Primary state keys:
- `dungeoncrawler_tester.runs`
- `dungeoncrawler_tester.stage_state`
- `dungeoncrawler_tester.auto_enqueue_last`

Gate summary:
- **Gate A (enqueue gate)**: active + no open issue + not pending/running + interval elapsed.
- **Gate B (execution gate)**: valid queue item metadata.
- **Gate C (failure gate)**: failed run forces stage inactive and optionally links issue.
- **Gate D (re-entry gate)**: remediation must clear/resolve paused conditions before natural re-enqueue.

## Timing and Blocking Matrix

| Segment | Type | Typical Trigger | Timeout/Cadence | Blocking Behavior |
|---|---|---|---|---|
| Auto-enqueue scan | Async scheduled | service invocation/cron | 3600s interval gate (default) | Non-eligible stage skipped |
| Queue item command run | Async worker | queue item available | 1800s timeout | Worker blocks for command duration |
| GitHub issue create | Sync network call inside worker | failed stage | 10s timeout | Worker blocks until response/timeout |
| Copilot REST assign | Sync network call inside worker | issue created | 10s per attempt | Worker blocks per attempt |
| Copilot CLI fallback | Local subprocess inside worker | REST attach not confirmed | 20s timeout | Worker blocks for CLI call |
| Dashboard queue action | Sync HTTP request | user action | request lifecycle | UI waits for response; queue may continue async |

## Recommended Update Policy

When automation behavior changes, update this file in the same PR as code changes affecting:
- Stage eligibility rules
- Queue timeouts/cadence
- Failure pause logic
- GitHub issue/assignment behavior
- Dashboard queue control behavior
