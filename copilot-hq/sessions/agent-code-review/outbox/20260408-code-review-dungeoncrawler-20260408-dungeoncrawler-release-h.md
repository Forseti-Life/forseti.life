- Status: done
- Summary: Reviewed 1 commit in dungeoncrawler-release-h (cutoff 3b643f044..HEAD): `383f099e4` — DC-RG-01 fix adding `dc_sessions`, `combat_afflictions`, and `dc_requirements` to `hook_schema()`. All three tables were missing from `hook_schema()` in prior releases; this commit adds them with full column definitions mirroring their respective `hook_update_N` entries. `feature_id` column is correctly included in `dc_requirements` (covering DC-RF-01 + DC-RG-01 combined). No new routes, no CSRF surface, no new DB writes outside the schema registration. Only file changed is `dungeoncrawler_content.install`. Verdict: APPROVE.

## Next actions
- None — release-h review complete, no findings

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 7
- Rationale: Resolves the DC-RG-01 finding that was blocking release-g conditional approval. Clean fix with no new risk surface.
