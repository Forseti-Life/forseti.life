# Suite Activation: dc-cr-rest-watch-starvation

- Status: done
- Summary: Suite activation for `dc-cr-rest-watch-starvation` confirmed. Entry `dc-cr-rest-watch-starvation-phpunit` (suite.json line 14394) was already present with 15 TCs covering watch schedule, daily prep sequence, thirst/starvation damage tracks, healing blocked while afflicted, edge cases (Con modifier ≤ 0 minimum, simultaneous tracks), and failure modes. The entry was missing `activated_release` — added `"activated_release": "20260412-dungeoncrawler-release-b"` and `"activated_date": "2026-04-12"`. All 15 TCs tagged `feature_id: "dc-cr-rest-watch-starvation"`, `status: "pending_implementation"`. Security AC exemption confirmed (no new routes, no permission rules required). One gap carried forward from grooming: TC-RWS-01 watch duration table values need BA confirmation from PF2E CRB ch10 before assertions are final — marked in suite notes. Suite validates clean. Committed `7fa36064b`.

## Next actions
- Await remaining suite activation items: dc-cr-skills-society-create-forgery
- Flag to BA/PM: TC-RWS-01 watch duration table values (party size 1–6 watch durations from PF2E CRB ch10 "Watches") needed before Dev can finalize that assertion

## Blockers
- None (TC-RWS-01 gap is a known pre-existing note, not a blocker for activation)

## ROI estimate
- ROI: 6
- Rationale: 15-TC suite for rest/survival mechanics; gates starvation/thirst implementation correctness and prevents silent regression of HP-recovery blocking logic.
