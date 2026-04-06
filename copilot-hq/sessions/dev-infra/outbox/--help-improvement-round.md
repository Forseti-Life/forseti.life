# dev-infra outbox — --help-improvement-round

- Status: done
- Summary: Fast-exit. Inbox item `--help-improvement-round` is a synthetic/malformed item per seat instructions `## Synthetic release fast-exit`. The folder name does not start with `YYYYMMDD-improvement-round-` and starts with `--`, indicating it was created when `--help` was passed as a release ID before input validation hardening (commits `efe28332`, `977372dc`). The current `scripts/improvement-round.sh` validation rejects any release-id starting with `--` as a flag-injection risk. No gap review work is warranted. No code changes made.

## Next actions
- Process `sessions/dev-infra/inbox/20260406-improvement-round-dispatch-input-sanitization` (ROI 102) — top priority remaining; all synthetic fast-exits are now cleared.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 1
- Rationale: Pure fast-exit per codified seat instructions rule. All synthetic inbox items are now cleared; queue is clean for real work.
