# Root cause: Gate 2 clean-audit backstop delay — 20260419-forseti-release-c

- Status: done
- Summary: The Gate 2 APPROVE for `20260419-forseti-release-c` was filed by the CEO backstop (`ceo-ops-once.sh`) at 2026-04-21T00:00:30 — ~17 hours after release activation — because two conditions combined: (1) the last site audit ran April 19 at 10:04 AM, before the release cycle activated at April 20 06:49 AM, so the primary path backstop (in `site-audit-run.sh`) ran against a different active release and had nothing to target; and (2) the hourly site-audit cron (`install-cron-site-audit-forseti.sh`) was never installed, so no fresh audit ran after the release started. Fixed both: installed the hourly audit cron and added an immediate site-audit trigger to `release-cycle-start.sh` so every new release cycle starts with a fresh audit (commit `e895bf019`). KB lesson written at `knowledgebase/lessons/20260421-gate2-audit-timing-gap.md`.

## Root cause
- **Primary blocker class:** GAP-GATE2-AUDIT-TIMING
- **Cause 1 (structural):** `install-cron-site-audit-forseti.sh` was never run on this host — no hourly audit cron existed. The audit only ran when manually triggered.
- **Cause 2 (sequencing):** `release-cycle-start.sh` queues QA preflight tasks but does not trigger a site audit. The audit predating the release by 21h meant the primary path backstop checked `tmp/release-cycle-active/forseti.release_id` and found a different release ID at audit time — Gate 2 was not filed.
- **Safety net worked:** `ceo-ops-once.sh` (every 2h) eventually saw clean audit + active release + no Gate 2 APPROVE and filed it, but this is a 2–18h delay window when cron is absent.

## Fixes applied (commit e895bf019)
1. **Installed hourly site-audit cron:** ran `bash scripts/install-cron-site-audit-forseti.sh` — now `15 * * * * scripts/site-audit-run.sh forseti-life` is active. Max gap is now 1 hour.
2. **`scripts/release-cycle-start.sh`:** added `bash scripts/site-audit-run.sh ${team_id}` immediately before the final echo — a fresh audit triggers the moment any release cycle activates, reducing typical Gate 2 lag to near-zero.

## Blocker class removed
- GAP-GATE2-AUDIT-TIMING: Gate 2 APPROVE delayed because audit predated release activation and audit cron was not installed.

## Next actions
- None required for this release (backstop already filed Gate 2 APPROVE)
- qa-forseti should complete the stale-audit re-run item (`20260421-syshealth-audit-stale-qa-forseti`) to refresh the audit for the current cycle
- No other gates blocked by this issue

## Blockers
- None

## ROI estimate
- ROI: 12
- Rationale: Closes an 18h Gate 2 delay window that will recur every release cycle. The hourly cron and release-activation trigger permanently reduce this to near-zero with no manual intervention required.

---
- Agent: ceo-copilot-2
- Source inbox: sessions/ceo-copilot-2/inbox/20260421-root-cause-gate2-clean-audit-forseti-20260419-forseti-release
- Commit: e895bf019
- Generated: 2026-04-21T06:22Z
