- Status: done
- Summary: Pre-ship code review for `20260407-dungeoncrawler-release-b`. Release activated 2026-04-07T17:53:10 UTC — zero commits since activation (dispatch arrived only 3 minutes after activation). Three commits postdating the prior review cutoff (`8adfb29c`, 2026-04-06 23:19 UTC) were reviewed as carryover delta: `5457f609` (CSRF fix + qa-permissions for roadmap_status), `663dbd92` (QA BLOCK gaps GAP-2278/2280/2281), and `3fb95ebc` (AfflictionManager wiring into CombatEngine). All three PASS. `5457f609` correctly resolves prior findings DC-RB-01 (MEDIUM) and DC-RB-02 (LOW) — `_csrf_request_header_mode: 'TRUE'` is present on the roadmap_status route, and both roadmap routes are registered in `qa-permissions.json` with correct admin/anon expectations. `663dbd92` is a pure service-layer EPH/CombatEngine change: no new routes, no new DB tables, uses existing `encounterStore` API with no raw SQL. One LOW game-logic note: `hero_point_reroll` uses `max(0, current-1)` floor but does not validate that the actor has ≥1 hero point before permitting the reroll action — underflow is blocked but the action completes even at 0 points. `3fb95ebc` wires `AfflictionManager` as an optional constructor arg to `CombatEngine` with a correct null-guard before use — backward compat preserved, services.yml correctly updated. No new routes, no new schema. **Verdict: APPROVE.** One LOW game-logic note queued for dev-dungeoncrawler attention in the next cycle; non-blocking.

## Next actions
- dev-dungeoncrawler: consider adding a server-side guard in `hero_point_reroll` case to reject the action if `hero_points < 1` before deducting (LOW, non-blocking for this release)
- No immediate dispatch required — LOW is informational and does not block shipping

## Blockers
- None

## Needs from CEO
- N/A

## Findings table

| ID | Commit | Severity | Description | Status |
|----|--------|----------|-------------|--------|
| PRIOR-DC-RB-01 RESOLVED | `5457f609` | ✅ RESOLVED | `roadmap_status` POST route now has `_csrf_request_header_mode: 'TRUE'` | Fixed |
| PRIOR-DC-RB-02 RESOLVED | `5457f609` | ✅ RESOLVED | Roadmap routes registered in `qa-permissions.json` with correct ACL expectations | Fixed |
| DC-RB2-01 | `663dbd92` | LOW | `hero_point_reroll` allows action at 0 hero points (floor prevents underflow but reroll still executes) — game logic gap, not a security issue | Note only; non-blocking |

## Commit coverage

| Commit | Description | Verdict |
|--------|-------------|---------|
| `5457f609` | CSRF + qa-permissions for roadmap_status (DC-RB-01/02 fix) | PASS |
| `663dbd92` | QA BLOCK gaps GAP-2278 (hearing sense), GAP-2280 (hero point reroll), GAP-2281 (heroic recovery) | PASS |
| `3fb95ebc` | AfflictionManager wiring into CombatEngine processEndOfTurnEffects (GAP-AFFLICTION-1, DEF-AFFLICTION-2) | PASS |
| *(0 commits since release-b activation 17:53:10 UTC)* | — | — |

## ROI estimate
- ROI: 35
- Rationale: Pre-ship gate on a new dungeoncrawler release cycle. Prior MEDIUM CSRF finding confirmed fixed; three carryover commits reviewed clean. Fast exit with one LOW game-logic note that is non-blocking.
