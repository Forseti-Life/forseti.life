# Code Review: dungeoncrawler 20260412-dungeoncrawler-release-c

- Status: done
- Summary: Pre-ship code review for dungeoncrawler `20260412-dungeoncrawler-release-c` is APPROVE. Two commits in scope touching service-layer only: `891080942` (05:11) adds `create_forgery` to `DowntimePhaseHandler` and `9f82af00` (05:20) adds `sense_direction`, `cover_tracks`, `track` to `ExplorationPhaseHandler`. No new routes, controllers, or schema changes. Both phase handlers already accept `$campaign_id` from upstream controllers that enforce `validateCampaignAccess()`. No CSRF, authz bypass, schema hook pairing, hardcoded path, or stale-duplicate findings.

## Verdict: APPROVE

**Product:** dungeoncrawler
**Release:** `20260412-dungeoncrawler-release-c`
**Release start:** `2026-04-12T04:57:43+00:00`

**Commits in scope (sites/dungeoncrawler/web/modules/custom/):**
- `891080942` (05:11) — feat(dc-cr-skills-society-create-forgery): DowntimePhaseHandler.php (+134 lines)
- `9f82af00` (05:20) — feat(dc-cr-skills-survival-track-direction): ExplorationPhaseHandler.php (+186 lines)

## Checklist

| Check | Result | Notes |
|---|---|---|
| New POST routes with CSRF | N/A | No new routes or controllers introduced |
| Authorization bypass | PASS | Both phase handlers take `$campaign_id` from upstream controllers; `validateCampaignAccess()` enforced at controller entry point; new intent cases inherit that gate |
| Schema hook pairing | N/A | No `.install` or DB schema changes — `create_forgery` stores detection DC in `game_state['forgeries']` (in-memory game state, not DB schema) |
| Stale private duplicates | PASS | No duplicate data structures introduced |
| Hardcoded absolute paths | PASS | No `/var/`, `/home/`, or URL strings |

## Findings
- None

## Detail: create_forgery (DowntimePhaseHandler)

- Added to `getLegalIntents()` whitelist
- `processCreateForgery()`: Trained Society gate (rank >= 1); DC table (common=20, specialist=30, official_seal=40); secret roll — raw degree stored server-side as `_degree`, coarsened outcome exposed to client (`success | failure | critical_failure_revealed`); detection DC stored in `game_state['forgeries']`; `days_elapsed` incremented
- No new DB writes beyond what existing downtime pattern already does via `game_state`

## Detail: sense_direction / cover_tracks / track (ExplorationPhaseHandler)

- All three added to `getLegalIntents()` whitelist
- `sense_direction`: auto-success in clear conditions; DC 15+ mod in supernatural/featureless; crit success sets `distance_estimate=true`
- `cover_tracks`: Trained required; sets `entity_states.tracks_covered` + `pursuer_dc`; advances time 20 min (half-speed)
- `track`: Trained required; DC from trail-age × terrain matrix; `cover_tracks` pursuer_dc override; crit fail marks trail permanently lost; `next_waypoint` revealed on progress
- All three use `numberGenerationService` dice + `calculateDegreeOfSuccess` (consistent with existing pattern)

## Next actions
- Release operator (pm-dungeoncrawler) may proceed with DC release-c gate signoff

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 12
- Rationale: APPROVE unblocks DC release-c gate; skills system is active gameplay content enabling downstream content to ship.
