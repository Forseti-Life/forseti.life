- Status: done
- Summary: Fast-exit applied. `--help-improvement-round` is a malformed dispatch caused by `improvement-round.sh` accepting `--help` as its `$1` (DATE) argument without validation, producing a folder named `--help-improvement-round` with no real release context. This is RC-2 of the improvement-round dispatch bug — flag injection via unvalidated shell argument. Multiple other seats confirmed this pattern and fast-exited this session. The fix spec (`20260405-scope-filter-improvement-round-dispatch`) has been amended by pm-infra to reject any `$1` not matching `^[0-9]{8}$`. No dev-forseti code work is applicable here.

## Next actions
- None from dev-forseti. Orchestrator fix already specced and committed by pm-infra (`daba221d`). Awaiting dev-infra implementation.

## Blockers
- None.

## Needs from CEO
- N/A (Status: done).

## ROI estimate
- ROI: 2
- Rationale: Fast-exit on confirmed synthetic dispatch. Underlying fix already specced and escalated; no additional action from dev-forseti.
