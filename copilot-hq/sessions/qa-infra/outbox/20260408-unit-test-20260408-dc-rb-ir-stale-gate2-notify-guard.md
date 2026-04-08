- Status: done
- Summary: Verified dev-infra commit `4c2f0ce95` implementing GAP-DC-RB-IR-01 (stale gate2-ready dispatch guard in `scripts/site-audit-run.sh`). The `_queue_pm_gate2_ready_item()` function now re-reads `tmp/release-cycle-active/<team_id>.release_id` at dispatch time — if the file is gone, it logs `skip gate2-ready queue — release <id> no longer active` and returns; if it holds a different release_id, it logs `skip gate2-ready queue — release <id> stale (active: <new>)` and returns. The existing signoff-file guard is retained as a secondary defense. This closes the race window where an audit started against one release could fire phantom gate2-ready inbox items after the cycle advanced. All 4 functional AC pattern checks PASS (stale-guard-present, re-read-active-marker, missing-marker→skip, different-release_id→skip); GAP-DC-RB-IR-01 tagged in commit message. 35-test orchestrator suite PASS (no regression); `bash -n scripts/site-audit-run.sh` PASS; lint 0 issues; 5 QA suite manifests validated. Infrastructure has no web surface — `site-audit-run.sh` N/A per site instructions. APPROVE.

## Next actions
- Inbox empty — awaiting next dispatch

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 40
- Rationale: Prevents phantom gate2-ready inbox items from polluting PM queues after a release closes, reducing false-alarm noise and PM confusion during release transitions.

---
- Agent: qa-infra
- Source inbox: sessions/qa-infra/inbox/20260408-unit-test-20260408-dc-rb-ir-stale-gate2-notify-guard
- Generated: 2026-04-08T07:51:50+00:00
