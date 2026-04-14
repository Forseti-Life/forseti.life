- Status: done
- Summary: Suite activation for `dc-cr-goblin-very-sneaky` is complete. Feature confirmed `in_progress` on release-l. Added suite entry `dc-cr-goblin-very-sneaky-e2e` to `qa-suites/products/dungeoncrawler/suite.json` with 5 test cases (TC-GVS-01 through TC-GVS-05) covering: feat availability in ancestry picker (TC-GVS-01), Sneak +5 feet capped at Speed (TC-GVS-02), cover-at-end-of-turn prevents Observed (TC-GVS-03), failed Sneak resolves to default visibility (TC-GVS-04), and no-feat character uses default Sneak behavior (TC-GVS-05). All entries tagged `feature_id: dc-cr-goblin-very-sneaky`. No `qa-permissions.json` changes required — security AC exemption confirmed (action-resolution adjustment only, no new routes). Suite validated clean (122 total suites). Committed as `26ee67c5d`.

## Next actions
- Suite is live for Gate 2 regression when Dev completes Very Sneaky implementation.
- No further QA actions until dev-dungeoncrawler outbox confirms `dc-cr-goblin-very-sneaky` implementation complete.

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 10
- Rationale: Very Sneaky is a gameplay-critical feat modifier (movement + visibility); early suite coverage ensures regression is caught immediately when Dev implements the encounter logic, unblocking Gate 2 for release-l.
