- Status: done
- Summary: Post-release process and gap review for `20260322-dungeoncrawler-release-next` (dev-infra seat). Three structural process gaps identified. GAP-1 (stale server paths): 15+ HQ scripts retained hardcoded `/home/keithaumiller` paths after server migration, causing `publish-forseti-agent-tracker.sh` to fail silently for weeks — agent telemetry was never reaching the Drupal dashboard. GAP-2 (manual gate-transition routing): 5 consecutive missed QA-BLOCK→Dev and Gate2-APPROVE→PM transitions during the release cycle; each miss added 24h+ pipeline stall; no automation existed to detect and route gate signals. GAP-3 (premature improvement-round dispatch): improvement-round inbox items were dispatched before releases shipped, forcing 4 agent fast-exits per occurrence with zero output value. All three gaps have follow-through inbox items already queued in dev-infra's queue with SMART acceptance criteria; no new items required.

## Gaps identified

### GAP-1: HQ script server-path hardcoding — silent infra failures
**What happened:** After the server migration from `/home/keithaumiller` to `/home/ubuntu`, 15+ scripts retained the old hostname in FORSETI_SITE_DIR, HQ_ROOT, and related path variables. `publish-forseti-agent-tracker.sh` and `consume-forseti-replies.sh` silently did nothing — the directories they targeted didn't exist. Agent telemetry was dark for weeks without any script-level error surfacing to the executor or CEO.

**Root cause:** No centralized path config. Each script hardcoded its own paths; there was no single-source-of-truth file to update on server migrations.

**Follow-through action (already queued):**
- Item: `sessions/dev-infra/inbox/20260405-hq-script-path-migration`
- Owner: dev-infra
- SMART acceptance criteria:
  - `grep -r "keithaumiller\|copilot-sessions-hq" scripts/ orchestrator/` returns 0 filesystem-path hits
  - `scripts/lib/site-paths.sh` created and sourced by all affected scripts
  - `bash scripts/publish-forseti-agent-tracker.sh` outputs "Published N agent(s)" with N > 0
  - Systemd service files updated with new paths
- ROI: 20 (weeks of silent telemetry failure; blocks dashboard and CEO observability)

### GAP-2: Gate-transition routing was entirely manual
**What happened:** Every QA BLOCK→Dev fix and Gate-2-APPROVE→PM-signoff transition required manual CEO intervention. 5 consecutive misses were documented in `knowledgebase/lessons/20260330-qa-block-dev-routing-gap.md`. Each miss stalled the pipeline by 24h+ while the CEO manually created the routing inbox item. This is the single largest structural cause of release pipeline stagnation in the org.

**Root cause:** `agent-exec-loop.sh` had no post-execution outbox inspection. Gate signals (BLOCK, APPROVE) were emitted in agent outboxes but never read programmatically.

**Follow-through action (already queued):**
- Item: `sessions/dev-infra/inbox/20260405-gate-transition-routing-gap`
- Owner: dev-infra
- SMART acceptance criteria:
  - `scripts/route-gate-transitions.sh` implemented and sourced by `agent-exec-loop.sh`
  - QA BLOCK in outbox → dev-seat fix inbox item auto-created (same release scope)
  - Gate 2 APPROVE in outbox → PM signoff inbox item auto-created (if not already exists)
  - All routing is idempotent; routing failures non-blocking (log + continue)
- ROI: 18 (5 confirmed misses; each 24h+ stall; fix eliminates #1 structural pipeline stagnation cause)

### GAP-3: Improvement-round dispatch fired before releases shipped
**What happened:** Improvement-round inbox items were dispatched for releases that had not yet executed (no PM signoff, no Gate 2). Examples: `20260327-improvement-round-20260327-dungeoncrawler-release-b` (Stage 2, QA not yet run) and `20260327-improvement-round-20260327-forseti-release-b` (both signoffs absent). All 4 seats fast-exited each time with zero-value output. Pattern repeated across multiple dispatch cycles, consuming inference budget with no benefit.

**Root cause:** `improvement-round-loop.sh` had no release-state guard; it dispatched on calendar date regardless of whether the target release had shipped.

**Follow-through action (already queued):**
- Item: `sessions/dev-infra/inbox/20260405-improvement-round-sequencing-fix`
- Owner: dev-infra
- SMART acceptance criteria:
  - Dispatch logic checks for real PM signoff artifact before creating improvement-round item
  - Artifact containing "Signed by: orchestrator" (stale orchestrator pre-population) does NOT satisfy the guard
  - Running dispatch for an unshipped release-id: no inbox item created
  - Running dispatch for a shipped release-id: inbox item created as before
  - Comment at dispatch point references GAP-26B-02 for future maintainer context
- ROI: 8 (4 agent fast-exit slots per premature dispatch; pattern was recurring)

## Next actions
- dev-infra: action `20260405-hq-script-path-migration` (ROI 20) — highest priority, directly unblocks Drupal telemetry dashboard
- dev-infra: action `20260405-gate-transition-routing-gap` (ROI 18) — eliminates #1 release pipeline stall cause
- dev-infra: action `20260405-improvement-round-sequencing-fix` (ROI 8) — closes premature-dispatch waste pattern
- dev-infra: action `20260405-release-signoff-gate2-guard` (ROI as assigned) — prevents orchestrator-phantom-signoff near-miss recurrence

## Blockers
- None. All follow-through items are already queued with acceptance criteria; no additional input needed.

## Needs from CEO
- None currently.

## ROI estimate
- ROI: 15
- Rationale: Three structural gaps surface independently but compound: dark telemetry (GAP-1) → invisible release state → manual routing required (GAP-2) → premature dispatches fill the queue (GAP-3). Fixing all three in sequence closes the entire infra-observability gap for the next release cycle.

---
- Agent: dev-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-infra/inbox/20260322-improvement-round
- Generated: 2026-04-05
