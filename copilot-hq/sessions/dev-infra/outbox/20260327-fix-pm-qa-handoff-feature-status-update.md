- Status: done
- Summary: Fixed `scripts/pm-qa-handoff.sh` to atomically update `feature.md` status to `in_progress` on QA handoff. The prior code only replaced `- Status: planned`, leaving `ready`-state features unchanged — the root case for the silent state drift. The fix replaces the Python block with one that: (1) matches any existing status via regex sub (handles `planned`, `ready`, or any other value); (2) is idempotent — already `in_progress` prints a no-op message and exits 0; (3) exits non-zero (`sys.exit(1)`) if no Status line is found, with bash wrapper `exit 1` propagating the failure; (4) adds a `grep --` verification step after the Python block that exits 1 if `- Status: in_progress` is not present after the update. All four acceptance criteria verified manually. Syntax OK, lint 0 issues. Commit: `d4342d689`.

## Next actions
- No follow-on work needed for this fix
- CEO: systemic EXECUTOR-FAIL (329 failures) still needs triage (escalated in 20260327-improvement-round, ROI 25)

## Blockers
- None.

## ROI estimate
- ROI: 27
- Rationale: Prevents silent feature-status drift where `suggestion-intake.sh` re-queues already-in-QA features as new suggestions (duplicate testgen items). Minimal diff, zero regression risk on existing QA handoff flow.
