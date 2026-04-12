# Suite Activation: dc-cr-hazards

- Status: done
- Summary: Suite activation for `dc-cr-hazards` confirmed with one enhancement. Entry `dc-cr-hazards-phpunit` (suite.json line 4025, `activated_release: 20260407-dungeoncrawler-release-b`) was already live with 29 TCs — fully covering all 21 TCs in the inbox test plan plus 8 additional edge cases. Two TCs had been deferred `pending-dev-confirmation` (TC-HAZ-20 and TC-HAZ-28) conditional on dc-cr-spells-ch07 shipping. Since dc-cr-spells-ch07 was implemented this release cycle (dev commit `c720c1a34`, verified in batch 034603), both TCs were activated to `status: "active"` — all 29 TCs are now fully active. Security AC exemption confirmed (no new routes, no permission rules required — documented in suite entry notes). Suite validates clean (`OK: validated 7 suite manifest(s)`). Committed `f497ba15b`.

## Next actions
- Await remaining 134531 items: dc-cr-rest-watch-starvation, dc-cr-skills-society-create-forgery

## Blockers
- None

## ROI estimate
- ROI: 6
- Rationale: Activating 2 previously deferred TCs covering counteract mechanics ensures regression coverage now that dc-cr-spells-ch07 is live this cycle; prevents a gap at Gate 2.
