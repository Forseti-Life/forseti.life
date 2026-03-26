# Quality Scoreboard — dungeoncrawler

> Update weekly. Track only a few metrics that drive behavior.

## 2026-03-26 — 20260322 coordinated release + release-b stall status

| Metric | Target | Actual | Notes |
|--------|--------|--------|-------|
| Post-merge regressions | 0 | 0 | `20260322-dungeoncrawler-release` + `dungeoncrawler-release-next` shipped clean. No user-reported defects. |
| Reopen rate (issues/PRs) | < 10% | N/A | No PR tracker configured. |
| Time-to-verify (median) | < 24h | N/A (release-b stalled) | `20260322-dungeoncrawler-release-b` stalled 4+ days awaiting qa-dungeoncrawler to apply dev-proposed qa-permissions.json fix (GAP-DC-STALL-01). |
| Escaped defects (prod/user reported) | 0 | 1 (new) | `/characters/create` production SSL handshake timeout (10.5s) found in Gate R5 audit `20260322-193507` (commit `ca3c9279a`). Not present in pre-push dev audit. Severity: unknown — triage required by pm-dungeoncrawler. |
| Consecutive unclean releases (post-release QA) | 0 | 0 | Shipped releases (`dungeoncrawler-release`, `dungeoncrawler-release-next`) passed Gate R5. Release-b unclean cycle count: 1 (permission regression false-positive; fix proposed but not applied). |
| Instructions-change proposals created | >= 1 when friction repeats | 1 | GAP-DC-STALL-01 escalation to CEO (commit `fd988824f`) — fix-pickup gap where dev→qa proposed fix is not being consumed. |

**Active gaps (not yet resolved):**
- **GAP-DC-STALL-01**: No executor routing rule for "dev proposed fix, QA has not picked it up." `20260322-dungeoncrawler-release-b` stalled since 2026-03-22. Escalated to CEO.
- **GAP-DC-01**: QA testgen throughput bottleneck — 4 features (`action-economy`, `ancestry-system`, `dice-system`, `difficulty-class`) blocked on test generation capacity. Active CEO escalation.
- **`/characters/create` SSL timeout**: Production-only finding (not in dev audit). pm-dungeoncrawler triage required.
- **`/campaigns` 403**: Pre-existing ACL-pending decision. Not a new regression.

## Baseline (rolling 7 days — 2026-02-27)
| Metric | Target | Actual | Notes |
|--------|--------|--------|-------|
| Post-merge regressions | 0 | 0 | No features shipped yet; maintenance releases only (release-b, release-c) |
| Reopen rate (issues/PRs) | < 10% | 0% | No features completed; N/A until first feature ships |
| Time-to-verify (median) | < 24h | N/A | No feature verifications completed yet |
| Escaped defects (prod/user reported) | 0 | 0 | No feature code shipped |
| Consecutive unclean releases (post-release QA) | 0 | 0 | Maintenance releases passed Gate 2 (audit `20260227-064041`, 0 violations) |
| Instructions-change proposals created | >= 1 when friction repeats | 2 | `e7cf3d8` (BASE_URL fix), `518d9d9` (pre-signoff verification step) |

## Top recurring failure modes (updated 2026-03-26)
- Automated QA audits targeted http://localhost (port 80 / forseti.life) instead of dungeoncrawler at http://localhost:8080 due to systemd service env var hardcode. Caused 5+ false-positive QA cycles across release-b/c. Root cause: `scripts/systemd/copilot-sessions-hq-site-audit.service` has `Environment=DUNGEONCRAWLER_BASE_URL=http://localhost`. Fix committed; `sudo systemctl daemon-reload` pending (dev-infra/CEO).
- QA automation generated 50+ duplicate findings inbox items for the same underlying failure (retry loop bug). Fixed by dev-infra (`20260225-manual-unblock-dungeoncrawler-42`).

## Guardrails added (tests/checklists/instructions)
- `org-chart/sites/dungeoncrawler/site.instructions.md` — corrected BASE_URL to `http://localhost:8080` (commit `e7cf3d8`).
- `org-chart/agents/instructions/pm-dungeoncrawler.instructions.md` — added mandatory pre-signoff BASE_URL verification step (commit `518d9d9`).
- `org-chart/sites/dungeoncrawler/qa-permissions.json` — added `no-destructive` first-match rule suppressing delete/archive/cancel paths from permission probing (commit `357230a`).
- **GAP-DC-STALL-01** (2026-03-26): No executor routing rule for "dev proposed fix → QA not consumed." Fix proposed by dev-dungeoncrawler in `sessions/dev-dungeoncrawler/outbox/20260322-193507-qa-findings-dungeoncrawler-30.md`; not applied by qa-dungeoncrawler after 4+ days. CEO escalation active (pm-forseti commit `fd988824f`). If this recurs: escalate immediately rather than waiting a full cycle.
- **GAP-DC-01** (2026-03-26): QA testgen throughput bottleneck blocking 4 features in `20260326-dungeoncrawler-release-b` cycle. CEO escalation active.
 
