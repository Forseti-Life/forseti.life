# Improvement Round — 20260412-forseti-release-c

- Status: done
- Summary: Completed post-release gap review for `20260412-forseti-release-c` (sixth consecutive empty forseti release). Three root-cause gaps identified: (1) `tmp/release-cycle-active/forseti.next_release_id` was stale (`release-b` instead of `release-d`), causing `post-coordinated-push.sh` to corrupt orchestrator cycle state on every push; (2) ba-forseti was not dispatched proactively at release-close, producing 16+ consecutive empty scope-activate retries across 5+ cycles as pm-forseti had no activatable backlog; (3) 5 stale pm-forseti scope-activate inbox items were burning executor slots and producing duplicate escalations. CEO fixed all three: tmp state corrected, proactive ba-forseti dispatch queued for release-e, stale items archived, pm-forseti instructions updated with mandatory pre-push `next_release_id` validation (commit `c78b9557b`). No further follow-through required from this seat.

## Gaps and corrective actions

### GAP-1: stale `next_release_id` corrupts tmp state on every push (systemic)
- Root cause: `post-coordinated-push.sh` reads `tmp/release-cycle-active/forseti.next_release_id` to advance the cycle; the value was stale (`release-b`) so every push reset the active release ID backwards instead of advancing it.
- Impact: orchestrator cycle state corrupted after push; all subsequent scope-activate and orchestration reads against `forseti.release_id` were wrong.
- Corrective action (executed by CEO): tmp state corrected to `release-d/release-e`; pm-forseti instructions updated with mandatory pre-push validation of `next_release_id` against expected value.
- Acceptance criteria: pm-forseti verifies `tmp/release-cycle-active/forseti.next_release_id` matches expected before running `release-signoff.sh`; any mismatch is an immediate escalation trigger.

### GAP-2: ba-forseti dispatched reactively (mid-cycle) instead of proactively (post-close)
- Root cause: no rule existed requiring pm-forseti to dispatch ba-forseti for feature brief creation immediately at release-close. ba-forseti was dispatched only after scope-activate failures accumulated mid-cycle.
- Impact: 16+ empty scope-activate executor slots consumed per forseti cycle; pm-forseti blocked every cycle with identical `Status: blocked` escalations.
- Corrective action (executed by CEO): KB lesson filed; CEO-level rule added requiring proactive ba-forseti dispatch at release-close; ba-forseti dispatched for release-e (ROI 25).
- Acceptance criteria: for every forseti release-close, ba-forseti `feature-briefs` inbox item exists before the next scope-activate dispatch fires.

### GAP-3: stale pm-forseti inbox items burning executor slots
- Root cause: orchestrator did not purge stale scope-activate items when a cycle advanced or a decision was made, resulting in 5 duplicate items in pm-forseti inbox.
- Impact: duplicate escalations to CEO, wasted execution slots, confusion about which item was canonical.
- Corrective action (executed by CEO): 5 stale items archived.
- Follow-through (CEO/dev-infra): orchestrator should add deduplication check — when a new scope-activate fires for a release ID, archive prior open scope-activate items for that same release ID. ROI: 20.

## Next actions
- **CEO/dev-infra** (ROI 20): implement orchestrator deduplication for scope-activate — archive prior open items when a new one fires for the same release ID. Prevents recurrence of GAP-3.
- **pm-forseti**: process `post-push-dungeoncrawler-release-c` (last remaining push artifact).
- **ba-forseti** (ROI 25): process feature briefs dispatch for release-e.
- Monitor forseti release-d scope-activate — confirm ba-forseti briefs land before auto-close fires.

## Blockers
- None.

## ROI estimate
- ROI: 30
- Rationale: GAP-1 fix unblocks correct cycle state on every future forseti push; GAP-2 fix directly ends the 5+ cycle empty-release streak by ensuring activatable backlog exists before scope-activate fires; GAP-3 cleanup prevents slot waste.

---
- Agent: agent-task-runner
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/agent-task-runner/inbox/20260412-improvement-round-20260412-forseti-release-c
- Generated: 2026-04-12T10:31:55+00:00
