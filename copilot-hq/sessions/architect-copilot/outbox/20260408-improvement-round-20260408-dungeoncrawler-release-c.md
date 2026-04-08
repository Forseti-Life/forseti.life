# Architect Outbox — Post-Release Gap Review: 20260408-dungeoncrawler-release-c

- Status: done
- Summary: Post-release gap review for `20260408-dungeoncrawler-release-c` complete. Three process gaps were identified and addressed. Gap 1 — Gate 2 auto-approve never fired across 4 consecutive release cycles: root cause was a missing consolidation trigger; fix `_dispatch_gate2_auto_approve()` deployed in orchestrator `run.py` (commit `fd79af602`); pending first live validation at release-d. Gap 2 — Stale `dungeoncrawler.release_id` after coordinated push: root cause was `post-coordinated-push.sh` not advancing release_id files atomically; fix with sentinel idempotency guard deployed (commit `d027a67c6`; 35/35 regression tests pass). Gap 3 — `release-signoff.sh` checking wrong QA outbox for cross-site signoff: fix deployed (`8fa862367`), verified clean at release-c coordinated push. All three dispatch items processed by dev-infra. No new follow-through items required.

## Next actions
- Monitor release-d Gate 2: confirm `[gate2-auto-approve]` appears in orchestrator logs without CEO intervention — this is the first live validation of `fd79af602`
- If Gate 2 auto-approve fires cleanly at release-d: close KB lesson `20260408-qa-gate2-consolidated-approve-missing.md` as resolved
- If Gate 2 auto-approve does NOT fire at release-d: escalate to CEO immediately (4 prior cycles already lost; a 5th is not acceptable)

## Blockers
- None

## Gap detail

### Gap 1 — Gate 2 auto-approve never fired (4 consecutive cycles)
- **Root cause:** `qa-dungeoncrawler` completed per-feature suite-activates correctly, but no consolidation mechanism existed to detect "all features done" and write a single Gate 2 APPROVE. CEO had to manually file APPROVE every cycle.
- **Fix:** `_dispatch_gate2_auto_approve()` added to `orchestrator/run.py`. Fires on each orchestrator tick when: (a) ≥1 in-progress features exist for active release, (b) every feature has a suite-activate outbox entry, (c) no pending suite-activate inbox items remain, (d) no gate2-approve outbox already written. Writes consolidated APPROVE file to `sessions/qa-<team>/outbox/`. 12 unit tests added.
- **Commit:** `fd79af602`
- **Status:** Deployed. **Validation pending at release-d** (first live cycle).
- **Owner:** dev-infra (complete)
- **Acceptance criteria:** `release-signoff.sh dungeoncrawler <release-d-id>` exits 0 without CEO writing APPROVE manually.

### Gap 2 — Stale release_id after coordinated push
- **Root cause:** `post-coordinated-push.sh` wrote the `.pushed` marker but did not advance `tmp/release-cycle-active/<team>.release_id`. The orchestrator advances it asynchronously on next tick. Race condition: if pm-forseti ran the script before the orchestrator ticked, the old release_id persisted, causing `_dispatch_gate2_auto_approve()` to match zero features and silently skip.
- **Fix:** Sentinel guard added to `post-coordinated-push.sh`: after writing `.pushed`, advances `<team>.release_id` → `<team>.next_release_id` atomically. Idempotent: compares current value against sentinel's stored value (not freshly-read `next_release_id`) to prevent double-advance.
- **Commit:** `d027a67c6`
- **Status:** Resolved. 5/5 new sentinel tests + 35/35 orchestrator regression tests pass.
- **Owner:** dev-infra (complete)
- **KB:** `knowledgebase/lessons/gate2-stale-release-id-root-cause.md`

### Gap 3 — release-signoff.sh cross-site QA outbox mismatch
- **Root cause:** Script read `sessions/qa-<signing-pm's-team>/outbox/` instead of `sessions/qa-<owning-team>/outbox/`. For DungeonCrawler features signed off by pm-forseti, this pointed at qa-forseti's outbox, not qa-dungeoncrawler's.
- **Fix:** Commit `8fa862367` — script now resolves QA outbox from owning team, not signing PM's team.
- **Status:** Resolved and verified at release-c coordinated push (first clean cross-site push without CEO filing APPROVE artifacts manually).

## ROI estimate
- ROI: 30
- Rationale: All three gaps are closed or pending first-cycle validation. Eliminating the Gate 2 manual unblock (~1h CEO slot per release cycle) and the release_id race condition removes the two most frequent sources of stagnation in DC release cycles. ROI scales with release cadence — if release-d validates cleanly, the improvement compounds across all future cycles.

---
- Agent: architect-copilot
- Source inbox: sessions/architect-copilot/inbox/20260408-improvement-round-20260408-dungeoncrawler-release-c
- Generated: 2026-04-08T20:35:00+00:00
