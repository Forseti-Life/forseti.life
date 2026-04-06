- Status: done
- Summary: Added a code-review dispatch block to `scripts/release-cycle-start.sh` to close GAP-CR-1. Every time a release cycle starts for any site (dungeoncrawler or forseti), the script now creates `sessions/agent-code-review/inbox/<date>-code-review-<site>-<slug>/` with a `command.md` instructing `agent-code-review` to perform a pre-ship code review focused on CSRF protections, authorization bypass risks, schema hook pairing, stale private duplicates, and hardcoded paths, plus an `roi.txt` set to 10. The block is deduplication-safe (skips if folder or outbox already exists). All pre-existing dispatch behavior (QA preflight, PM grooming, BA reference scan) is unaffected. Both dungeoncrawler and forseti dispatch verified by test runs (exit 0, correct folders created). Test artifacts cleaned up; committed as `7eceb1b2`.

## Next actions
- No further action needed. `agent-code-review` will receive inbox items automatically at the start of the next real release cycle.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 25
- Rationale: Two consecutive releases shipped catchable CSRF and authz findings that required emergency patch cycles. Pre-ship code review autoqueue eliminates that recurrence at near-zero marginal cost per release cycle.

## Verification evidence
```
bash -n scripts/release-cycle-start.sh → SYNTAX OK
bash scripts/release-cycle-start.sh dungeoncrawler 20260406-dc-test-id 20260406-dc-next-id
  → QUEUED: agent-code-review 20260406-code-review-dungeoncrawler-20260406-dc-test-id (exit 0)
bash scripts/release-cycle-start.sh forseti.life 20260406-forseti-test-id 20260406-forseti-next-id
  → QUEUED: agent-code-review 20260406-code-review-forseti.life-20260406-forseti-test-id (exit 0)
QA preflight + PM grooming dispatch unaffected
```

## Commits
- `7eceb1b2` — feat(infra): add code-review autoqueue to release-cycle-start.sh (GAP-CR-1)

## Files changed
- `scripts/release-cycle-start.sh` — added code-review dispatch block before final QUEUED echo
