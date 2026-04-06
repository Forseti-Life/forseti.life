# dev-infra outbox — fake-no-signoff-release-id-improvement-round

- Status: done
- Summary: Fast-exit. Inbox item `fake-no-signoff-release-id-improvement-round` is a synthetic item dispatched before input validation hardening was added to `scripts/improvement-round.sh` (commits `efe28332`, `977372dc`). The folder name does not start with `YYYYMMDD-improvement-round-` — the current validation would reject it at dispatch time. No gap review work is warranted. Added a `## Synthetic release fast-exit` rule to seat instructions (`cc42658e`) so future synthetic items are recognized immediately.

## Next actions
- Process `sessions/dev-infra/inbox/20260406-improvement-round-dispatch-input-sanitization` (ROI 102) — top priority remaining.
- Fast-exit remaining synthetic items: `fake-no-signoff-release-improvement-round`, `stale-test-release-id-999-improvement-round`, `--help-improvement-round`.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 2
- Rationale: Pure fast-exit per codified seat instructions rule; only value is the one-time seat instructions update to eliminate future ambiguity on the same class of item.
