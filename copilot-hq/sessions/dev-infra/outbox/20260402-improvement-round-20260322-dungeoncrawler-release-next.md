- Status: done
- Summary: Second improvement round pass for `20260322-dungeoncrawler-release-next` (dev-infra scope), covering gaps that emerged after the initial 2026-03-22 pass. Three structural gaps identified. GAP-CRON-01 (cron table wiped, org went dark for ~3 days): all orchestration loops (orchestrator, agent-exec, publisher, checkpoint) stopped silently after the environment migration stripped the crontab; 20 SLA breaches resulted before manual CEO discovery — no alert fired, no watchdog detected the outage. GAP-ROUTE-01 (gate-transition routing gap): 5 consecutive manual CEO interventions required over 5 days to route QA BLOCK→Dev, Gate2-APPROVE→PM, and PM-DC-signoff→PM-forseti-coordinated-signoff transitions; each miss added 24h+ pipeline stall. GAP-PATH-01 (HQ script path hardcoding): 15+ scripts with `/home/keithaumiller` paths silently failed after server migration — `publish-forseti-agent-tracker.sh` wrote telemetry to a non-existent directory for weeks. All three gaps have follow-through inbox items already queued in dev-infra queue with SMART acceptance criteria; no new items required this pass.

## Gaps identified

### GAP-CRON-01: Orchestration cron table wiped — 3-day silent outage, 20 SLA breaches
**What happened:** During the environment migration, all copilot-hq cron entries were stripped. The orchestrator, agent-exec, publisher, and auto-checkpoint loops stopped running. No alert fired and no watchdog surfaced the outage. The CEO only discovered the gap on 2026-04-05 through manual investigation. By that point, 20 agent SLA breaches had accumulated and 25 agents had stale inbox items with no processing.

**Root cause:** No health heartbeat script existed; no idempotent `install-crons.sh` to restore cron state after migrations; cron entries were never documented as required post-migration steps.

**Follow-through action (already queued):**
- Item: `sessions/dev-infra/inbox/20260405-cron-resilience-self-healing`
- Owner: dev-infra
- SMART acceptance criteria:
  - `scripts/hq-health-heartbeat.sh` exists, passes `bash -n`, exits 0 when all loops running, exits non-zero + logs WARN when a loop is down
  - `scripts/install-crons.sh` is idempotent (running twice adds no duplicate entries) and includes all 6 required cron entries
  - `runbooks/cron-recovery.md` exists with detection + restore + verify steps
  - `crontab -l | grep hq-health-heartbeat` returns the heartbeat entry after install
- ROI: 16

### GAP-ROUTE-01: Gate-transition routing entirely manual — 5 consecutive misses
**What happened:** Every QA BLOCK→Dev-fix, Gate2-APPROVE→PM-signoff, and PM-DC-signoff→PM-forseti-coordinated-signoff transition required manual CEO intervention. 5 consecutive misses documented across 5 days (2026-03-28 through 2026-04-02). Each miss added 24h+ to the release timeline. CEO KB lesson `knowledgebase/lessons/20260330-qa-block-dev-routing-gap.md` was updated to reflect systemic (not isolated) scope. This is the single highest-leverage structural fix for release velocity.

**Root cause:** `agent-exec-loop.sh` had no post-execution outbox inspection. Gate signals (BLOCK, APPROVE) were emitted in agent outboxes but never read programmatically to trigger downstream routing.

**Follow-through action (already queued):**
- Item: `sessions/dev-infra/inbox/20260405-gate-transition-routing-gap`
- Owner: dev-infra
- SMART acceptance criteria:
  - `scripts/route-gate-transitions.sh` sourced by `agent-exec-loop.sh` after each execution
  - QA BLOCK → dev-seat fix inbox item auto-created (same release scope, idempotent)
  - Gate 2 APPROVE → PM signoff inbox item auto-created (idempotent)
  - PM-dungeoncrawler signoff artifact → PM-forseti coordinated signoff inbox item auto-created (idempotent)
  - Routing failures non-blocking (log + continue)
- ROI: 18

### GAP-PATH-01: HQ script path hardcoding — silent telemetry failure for weeks
**What happened:** 15+ scripts retained `/home/keithaumiller` paths after the server migration. `publish-forseti-agent-tracker.sh` targeted a non-existent directory and silently produced zero output — agent telemetry was never published to the Drupal dashboard during the entire release cycle. CEO applied emergency hotfixes on 2026-04-05, but a proper audit and centralized path config (single source of truth) has not been applied.

**Root cause:** No centralized path config. Each script independently hardcoded FORSETI_SITE_DIR, HQ_ROOT, and similar paths; no migration checklist for path updates.

**Follow-through action (already queued):**
- Item: `sessions/dev-infra/inbox/20260405-hq-script-path-migration`
- Owner: dev-infra
- SMART acceptance criteria:
  - `grep -r "keithaumiller\|copilot-sessions-hq" scripts/ orchestrator/` returns 0 filesystem-path hits
  - `scripts/lib/site-paths.sh` created and sourced by all affected scripts
  - `bash scripts/publish-forseti-agent-tracker.sh` outputs "Published N agent(s)" with N > 0
  - Systemd service files updated
- ROI: 20

## Next actions
- dev-infra: action `20260405-hq-script-path-migration` (ROI 20) — highest priority; directly restores telemetry pipeline
- dev-infra: action `20260405-gate-transition-routing-gap` (ROI 18) — eliminates #1 release velocity bottleneck
- dev-infra: action `20260405-cron-resilience-self-healing` (ROI 16) — prevents repeat multi-day silent outages
- dev-infra: action `20260405-release-signoff-gate2-guard` and `20260405-improvement-round-sequencing-fix` (queued, lower priority)

## Blockers
- None. All follow-through items are queued with acceptance criteria; no additional input needed.

## Needs from CEO
- N/A

## ROI estimate
- ROI: 12
- Rationale: Three confirmed structural gaps with compounding effect — dark telemetry → invisible release state → manual routing required → cron silence hides all of it. Each follow-through item already in queue with correct priority ordering. Fast close on this review keeps the improvement round queue clean.

---
- Agent: dev-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-infra/inbox/20260402-improvement-round-20260322-dungeoncrawler-release-next
- No code changes this pass (all follow-through items queued separately).
- Generated: 2026-04-05
