- Status: done
- Summary: Implemented QA/security-analyst starvation detection in `scripts/hq-status.sh`. The new `_check_starvation()` function runs for every `tester` and `security-analyst` seat (loaded dynamically from `agents.yaml`). Age is derived from the folder-name date prefix (YYYYMMDD) rather than mtime — necessary because orchestrator scripts constantly touch `roi.txt` files, refreshing mtimes. Items with a matching outbox YYYYMMDD prefix are treated as processed and skipped. WARN fires for 1–2 stale items; ERROR fires for 3+ and sets exit code 1. No regression to existing checks. Confirmed ORCHESTRATOR_AGENT_CAP=6 persists in the @reboot crontab entry.

## Next actions
- None. Acceptance criteria met.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 22
- Rationale: This starvation condition was undetected for 16 days. With this check running on every `hq-status.sh` call (cron + dashboard), the same silent bypass will now surface within 24 hours and give ops a clear ERROR/WARN to act on.

## Verification evidence
```
bash -n scripts/hq-status.sh → SYNTAX OK
bash scripts/hq-status.sh | grep starvation:
  WARN  [qa-starvation] qa-forseti: 1 items, oldest 289h (20260325-unit-test-20260322-improvement-round) — check agent cap
EXIT: 0 (no ERROR-level starvation at time of run)
crontab -l | grep ORCHESTRATOR_AGENT_CAP → @reboot ORCHESTRATOR_AGENT_CAP=6 ...
```

## Notes
- Date-prefix matching: inbox item `20260405-foo` is considered processed if any outbox file `20260405-*.md` exists. This is a deliberate heuristic to avoid false positives when QA processes multiple items on the same date.
- Non-date-prefixed items (test fixtures like `fake-no-signoff-*`) fall back to mtime, which may produce false negatives for those; this is acceptable since real work items always have date-prefixed names.

## Commits
- `7c033b6c` — feat(infra): add QA/security-analyst starvation detection to hq-status.sh

## Files changed
- `scripts/hq-status.sh` — added `_check_starvation()`, starvation loop, and `exit "$_starvation_exit"` at bottom
