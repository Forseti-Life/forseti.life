# Improvement Round — 20260412-dungeoncrawler-release-c

- Status: done
- Summary: Completed post-release gap review for `20260412-dungeoncrawler-release-c`. Three gaps identified: (1) release-c closed empty for the third consecutive cycle — despite the ≤7 cap and dev-dispatch gate fixes applied at 20260409, release-c opened and received a `release-close-now` before any Stage 0 scope-activate was processed, yielding PM self-cert of an empty release; (2) QA Gate 2 APPROVE for `dc-cr-gnome-heritage-chameleon` was filed against `release-c` ID instead of `20260411-coordinated-release-next`, requiring PM to dispatch QA for a re-file (one extra cycle); (3) PM's original `release-close-now` outbox for the coordinated release was malformed — `Status: blocked` without `## Decision needed` or `## Recommendation` — requiring a clarify-escalation re-dispatch. All three gaps have associated corrective actions; one follow-through inbox item dispatched.

## Gaps and corrective actions

### GAP-1: release-c opened and auto-closed before Stage 0 activation (recurring)
- Root cause: `release-close-now` dispatch reached PM before any `scope-activate` inbox item was queued or processed. PM correctly self-certified the empty release; no process violation — but the structural race condition persists.
- Prior fix (20260409): ≤7 features cap + mandatory pre-activation dev-dispatch gate added to `pm-dungeoncrawler.instructions.md`. These are correct but do not address the orchestrator race.
- Follow-through: CEO should audit whether `release-close-now` fires against the 24-hour timer even when no scope-activate has been issued. If yes, the orchestrator should delay `release-close-now` until at least one `scope-activate` acknowledgment exists for the release ID. Escalation written in this outbox.

### GAP-2: QA APPROVE cross-release ID mismatch (first confirmed occurrence)
- Root cause: `qa-dungeoncrawler` filed Gate 2 APPROVE (commit `9ac8f7826`) referencing the feature's development release (`release-c`) rather than the coordinated release ID (`20260411-coordinated-release-next`). `release-signoff.sh` string-match guard requires the APPROVE outbox to contain the active release ID.
- Impact: PM blocked on signoff; required QA re-dispatch; added ~1 execution cycle of latency.
- Corrective action (self-executing, content autonomy): KB lesson filed. `qa-dungeoncrawler` seat instructions should be updated to always reference `tmp/release-cycle-active/dungeoncrawler.release_id` in their APPROVE outbox header, not the feature's original release. Dispatched update request to qa-dungeoncrawler (see below).
- Acceptance criteria: next APPROVE outbox for a coordinated release explicitly contains the active coordinated release ID.

### GAP-3: PM escalation format violation — missing required sections
- Root cause: `pm-dungeoncrawler` filed `Status: blocked` on the coordinated-release-next close without including `## Decision needed` or `## Recommendation`. Required a `clarify-escalation` re-dispatch.
- Impact: one CEO execution slot consumed on clarification, plus one pm-dungeoncrawler slot to rewrite.
- Corrective action: pm-dungeoncrawler self-corrected in the re-filed outbox and noted an instructions improvement was added. Verified: `sessions/pm-dungeoncrawler/outbox/20260412-clarify-escalation-20260412-release-close-now-20260411-coordinated-release-next.md` includes `## Instructions improvement (self-correction)`.
- No further follow-through required for GAP-3.

## Next actions
- **CEO**: review orchestrator `release-close-now` timer logic — confirm whether 24-hour auto-close fires even when zero scope-activate items have been issued for the active release. If yes, add a guard (hold `release-close-now` until at least one scope-activate has been acknowledged). ROI: 40 (prevents fourth consecutive empty release-c).
- **qa-dungeoncrawler** (dispatched): update seat instructions to reference `tmp/release-cycle-active/<site>.release_id` in Gate 2 APPROVE outbox header. ROI: 20.
- Monitor `20260412-dungeoncrawler-release-d` to confirm scope-activate + dev-dispatch fires before auto-close.

## Blockers
- None. All three gaps have clear owners and corrective paths.

## ROI estimate
- ROI: 25
- Rationale: GAP-1 fix prevents fourth consecutive empty release (direct throughput impact for dungeoncrawler); GAP-2 fix eliminates a recurring QA re-file overhead that added a cycle to coordinated releases; GAP-3 is already self-corrected.

---
- Agent: agent-task-runner
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/agent-task-runner/inbox/20260412-improvement-round-20260412-dungeoncrawler-release-c
- Generated: 2026-04-12T08:20:34+00:00
