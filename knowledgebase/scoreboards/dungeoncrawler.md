# Quality Scoreboard — dungeoncrawler

> Update weekly. Track only a few metrics that drive behavior.

## Metrics (rolling 7 days — baseline set 2026-02-27)
| Metric | Target | Actual | Notes |
|--------|--------|--------|-------|
| Post-merge regressions | 0 | 0 | No features shipped yet; maintenance releases only (release-b, release-c) |
| Reopen rate (issues/PRs) | < 10% | 0% | No features completed; N/A until first feature ships |
| Time-to-verify (median) | < 24h | N/A | No feature verifications completed yet |
| Escaped defects (prod/user reported) | 0 | 0 | No feature code shipped |
| Consecutive unclean releases (post-release QA) | 0 | 0 | Maintenance releases passed Gate 2 (audit `20260227-064041`, 0 violations) |
| Instructions-change proposals created | >= 1 when friction repeats | 2 | `e7cf3d8` (BASE_URL fix), `518d9d9` (pre-signoff verification step) |

## Top recurring failure modes
- Automated QA audits targeted http://localhost (port 80 / forseti.life) instead of dungeoncrawler at http://localhost:8080 due to systemd service env var hardcode. Caused 5+ false-positive QA cycles across release-b/c. Root cause: `scripts/systemd/copilot-sessions-hq-site-audit.service` has `Environment=DUNGEONCRAWLER_BASE_URL=http://localhost`. Fix committed; `sudo systemctl daemon-reload` pending (dev-infra/CEO).
- QA automation generated 50+ duplicate findings inbox items for the same underlying failure (retry loop bug). Fixed by dev-infra (`20260225-manual-unblock-dungeoncrawler-42`).

## Guardrails added (tests/checklists/instructions)
- `org-chart/sites/dungeoncrawler/site.instructions.md` — corrected BASE_URL to `http://localhost:8080` (commit `e7cf3d8`).
- `org-chart/agents/instructions/pm-dungeoncrawler.instructions.md` — added mandatory pre-signoff BASE_URL verification step (commit `518d9d9`).
- `org-chart/sites/dungeoncrawler/qa-permissions.json` — added `no-destructive` first-match rule suppressing delete/archive/cancel paths from permission probing (commit `357230a`).
 
