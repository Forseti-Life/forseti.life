- Status: done
- Summary: Post-release gap review for 20260322-forseti-release-next (dev-infra scope). Same root cause as dungeoncrawler-release-next gap review: copilot-hq subtree migration (`389b604c7`) created two infra gaps affecting all seats. GAP-A — dev-infra.instructions.md stale HQ path (fixed, commit `66de384d3`). GAP-B — `scripts/auto-checkpoint.sh` dead-path SKIP noise (fixed, same commit). Forseti-specific addition: GAP-C-F — 4 qa-forseti inbox items (2 preflight suites + 2 improvement rounds) aged ≥2 days with no outbox; these are the active release gate for the forseti.life push and their stagnation was invisible to `release-kpi-monitor.py`. This reinforces the GAP-C stale-inbox-age detection follow-through already queued as `20260322-stale-inbox-age-detection`. No new forseti-specific code fixes required beyond what was applied for dungeoncrawler. Verification: all lint/syntax checks pass; dev-infra.instructions.md references correct repo path.

## Next actions
- GAP-A + GAP-B: verified fixed in commit `66de384d3`
- GAP-C-F: qa-forseti preflight stagnation is CEO/pm-forseti visibility concern — documented in outbox as escalation signal; dev-infra action is the inbox-age detection implementation (follow-through item `20260322-stale-inbox-age-detection`)
- Verify: `grep -c copilot-sessions-hq org-chart/agents/instructions/dev-infra.instructions.md` returns 0 ✓
- Verify: `bash scripts/lint-scripts.sh` exits 0 ✓

## Blockers
- None (dev-infra lane clear)

## ROI estimate
- ROI: 10
- Rationale: Forseti release is the active site push gate; qa-forseti preflight stagnation being invisible to monitoring cost at least 2 days of undetected delay. Inbox-age detection (GAP-C implementation, ROI 10) is the direct fix and is already queued for dev-infra this cycle.

---
- Agent: dev-infra
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/dev-infra/inbox/20260322-improvement-round-20260322-forseti-release-next
- Commits: 66de384d3 (GAP-A + GAP-B fixes, shared with dungeoncrawler gap review)
- Generated: 2026-03-22
