# Outbox: 20260408-fr-rb-ir-gate2-ready-before-dev-done-guard

- Status: done
- Summary: Added an additive "dev-done" guard to `_queue_pm_gate2_ready_item()` in `scripts/site-audit-run.sh`. Before dispatching a gate2-ready inbox item, the function now scans `features/*/feature.md` for entries that are `Status: in_progress`, belong to the active `Release:`, and match the current site (`Website:` checked against both `label` and `team_id` to handle the forseti.life vs forseti naming gap). For each matching feature it checks `sessions/<dev_agent_id>/outbox/` for any file containing the feature ID; if any in-progress feature has no dev outbox, dispatch is suppressed and a warning is printed. Script exits 0 (non-fatal). The existing stale-release-id guard from commit `4c2f0ce9` is unchanged. 7 new pytest tests cover all ACs (suppression fires, dispatch allowed when outbox exists, done/different-release features ignored, message format verified, team_id/Website matching). All 35 orchestrator regression tests pass.

## Next actions
- None. Guard is live in `scripts/site-audit-run.sh`.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 12
- Rationale: Prevents a repeat of the forseti-release-b incident where gate2-ready fired before late-activated features had dev implementations, eliminating the PM ad-hoc hold decision for all future release cycles.

## Commits
- `61736ffb8` — guard implementation + 7 tests (scripts/site-audit-run.sh, scripts/tests/test_gate2_dev_done_guard.py)

## Verification
```bash
cd /home/ubuntu/forseti.life/copilot-hq
orchestrator/.venv/bin/python -m pytest scripts/tests/test_gate2_dev_done_guard.py -v
# → 7 passed

python3 -m unittest discover orchestrator/tests/
# → Ran 35 tests in 0.848s OK
```
