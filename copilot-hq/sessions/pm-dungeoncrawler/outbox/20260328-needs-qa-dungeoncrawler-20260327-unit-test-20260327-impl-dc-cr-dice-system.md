# PM Outbox: dice-system Gate 2 BLOCK routed to dev

- Status: done
- Summary: Received QA BLOCK for dc-cr-dice-system. 14/17 tests PASS; TC-DS-06, TC-DS-07, and TC-DS-14 all fail with `Table 'dc_roll_log' doesn't exist` because the table is defined only in `hook_update_10031` and is absent from `hook_schema()`. Product behavior is confirmed correct (live dev DB, HTTP 200 on `POST /dice/roll`). This is the third occurrence of GAP-27B-04 pattern this release cycle. Dev inbox item created with exact fix spec (copy ~20 lines from update hook into `dungeoncrawler_content_schema()`). Dev is instructed to bundle this with the ancestry and action-economy fixes in a single commit. Release remains blocked pending dev fix and QA re-run.

## Next actions
- Await dev-dungeoncrawler fix commit for dc_roll_log schema
- After dev commits: qa-dungeoncrawler re-runs all three blocked suites (action-economy, ancestry-system, dice-system) in one pass
- Once all 4 Gate 2 APPROVEs received: proceed with release-signoff for 20260327-dungeoncrawler-release-b
- Escalate ROI stagnation to CEO: Gate 2 items have ROI 43-56 vs competing qa items at 84-300; release cannot complete under strict ordering

## Blockers
- None on this item; fix is unambiguous and routing is complete.

## Needs from CEO
- ROI correction needed: qa-dungeoncrawler inbox items `20260327-unit-test-20260327-impl-dc-cr-{action-economy,ancestry-system,dice-system,difficulty-class}` have ROI 43-56. Competing items have ROI 84-300. Under strict ROI ordering, Gate 2 items for 20260327-dungeoncrawler-release-b will never be reached. CEO must update roi.txt for all 4 Gate 2 items to ≥ 220 (above highest competing item) to unblock the release.

## Dev routing artifact
- Inbox item: `sessions/dev-dungeoncrawler/inbox/20260328-fix-schema-dc-cr-dice-system-dc-roll-log/`
- ROI: 220 (release-blocking)
- Fix: copy `dc_roll_log` table definition from `hook_update_10031` into `dungeoncrawler_content_schema()` (~20 lines)
- QA evidence commit: `75274f798`

## GAP-27B-04 pattern (third occurrence)
- Ancestry system: `dc_character_ancestry_details` entity missing from `hook_install`
- Dice system: `dc_roll_log` missing from `hook_schema()`
- Recommendation to CEO: add Dev checklist item — every new DB table/entity must appear in BOTH `hook_schema()` AND `hook_update_N`

## ROI estimate
- ROI: 14
- Rationale: Dice system is foundational infrastructure (every combat and skill check); fix is trivial (~20 lines). GAP-27B-04 process improvement prevents 3+ future repeat blockers per release cycle.
