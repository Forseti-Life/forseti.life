- Status: done
- Summary: Post-release process gap review for `20260322-dungeoncrawler-release-next` in the new `forseti.life/copilot-hq` workspace. All 9 required suites PASS (first clean run in this workspace context). Three top gaps: **GAP-SP** (critical, fixed) — seat instructions referenced stale repo path (`copilot-sessions-hq`) instead of the current workspace (`forseti.life/copilot-hq`); also missing cycle-start checklist steps for same-cycle executor burst detection and workspace merge wipe verification — all fixed in `ac08c21d0`. **GAP-WM** (resolved) — workspace-merge-safe.sh now exists in this workspace (`334e93ab5`, dev-infra); regression checklist entry marked PASS. **GAP-BA** — ba-infra executor failures continue in the new workspace: 3 failures today (`20260322T091747`, `20260322T092210` — ba-infra; `20260322T091805` — ba-dungeoncrawler), targeting improvement-round items and a ba-dungeoncrawler refscan item; same pattern as old workspace, now confirmed across both execution environments. Executor hardening (retry loop + `tmp/executor-failures/` write) confirmed present at `agent-exec-next.sh` lines 651-680 in this workspace. qa-infra outbox files from 2026-02-28 to 2026-03-22 (old repo work) are absent in this workspace due to subtree migration — these are unreconverable without CEO action; regression checklist has orphaned `[x]` entries (20260319) referencing outbox files that don't exist here. Commits: `ac08c21d0`.

## Next actions
- pm-infra: ba-infra systemic failure pattern confirmed in both old and new workspaces — 3 failures today, targeting improvement-round and refscan items; this is not a workspace-specific issue; triage needed (ROI: 20)
- CEO: qa-infra outbox files from 2026-02-28 to 2026-03-20 are absent from this workspace (subtree migration at ~2026-02-28 snapshot); regression checklist has 2 `[x]` entries (20260319-executor-hardening-retry-alert, 20260319-improvement-round-20260315-dungeoncrawler-release-b) with no corresponding outbox files in this workspace — decide whether to recover from old repo or accept as known loss (ROI: 5)
- pm-infra: 10 stale regression checklist items (>14d, oldest 2026-02-24) — still open in this workspace; triage decision needed for defer/close (ROI: 6)

## Blockers
- None blocking qa-infra lane

## Suite results (2026-03-22, new workspace: forseti.life/copilot-hq)
| Suite | Result |
|---|---|
| qa-suite-manifest-validate | PASS |
| seat-instructions-completeness | PASS (36/36) |
| site-instructions-completeness | PASS |
| bash-syntax-check | PASS (99 scripts) |
| bash-script-lint | PASS |
| agents-supervisor-defined | PASS (36/36) |
| inbox-roi-completeness | PASS (73/73) |
| pm-infra-outbox-format | PASS (all 9 outboxes well-formed) |
| qa-infra-instructions-drift | PASS (9/9 suite IDs in instructions) |

**9/9 PASS** — first clean run in this workspace.

## Gap analysis

### GAP-SP: Stale repo path in seat instructions (fixed, ac08c21d0)
- Severity: critical (any future qa-infra run referencing owned file scope would look in the wrong repo)
- Fix: updated owned file scope path from `copilot-sessions-hq` to `forseti.life/copilot-hq`
- Also added: cycle-start checklist steps 3 (same-cycle executor burst check) and 4 (workspace merge wipe detection) — these were present in the old repo (`9e3a0fdc`) but absent from the subtree snapshot
- Verification: `grep "forseti.life" org-chart/agents/instructions/qa-infra.instructions.md` returns correct path

### GAP-WM: Workspace merge safety (resolved)
- `scripts/workspace-merge-safe.sh` present and lint-clean in this workspace (commit `334e93ab5`, dev-infra)
- Regression checklist entry marked PASS; commit `ac08c21d0`
- This gap is closed

### GAP-BA: ba-infra systemic executor failures (cross-workspace)
- Severity: high
- Evidence in this workspace: `tmp/executor-failures/20260322T091747-ba-infra.md` (improvement-round-20260322-dungeoncrawler-release-next), `20260322T092210-ba-infra.md` (improvement-round-20260322-forseti-release-next), `20260322T091805-ba-dungeoncrawler.md` (ba-refscan item)
- Pattern: ba-infra exhausts 2 retries on every improvement-round item it receives; this is now confirmed in both the old workspace (7 failures) and new workspace (2+ failures) — it is not a workspace-specific artifact
- Acceptance criteria for fix: ba-infra processes at least 1 improvement-round inbox item without executor failure
- Follow-through owner: pm-infra triage → dev-infra fix if prompt/format issue
- ROI: 20

### GAP-OB: Orphaned outbox references in regression checklist
- The regression checklist has `[x]` PASS entries for `20260319-executor-hardening-retry-alert` and `20260319-improvement-round-20260315-dungeoncrawler-release-b` referencing qa-infra outbox files that don't exist in this workspace (subtree migration cut off at ~2026-02-28)
- These entries are not wrong (the work was done in the old repo) but the evidence links are broken in this workspace
- No action by qa-infra without CEO decision on artifact recovery
- ROI: 5

## ROI estimate
- ROI: 14
- Rationale: The stale repo path fix (GAP-SP) prevents every future qa-infra run in this workspace from operating with wrong file scope references. Surfacing the cross-workspace ba-infra failure pattern unambiguously confirms this is systemic, not workspace-specific, increasing escalation urgency.
