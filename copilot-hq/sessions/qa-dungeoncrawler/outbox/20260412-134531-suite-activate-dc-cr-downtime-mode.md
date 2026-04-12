- Status: done
- Summary: Suite activation for `dc-cr-downtime-mode` is already complete — no new entries needed. Both suite entries were activated in the prior release cycle (release-h, 2026-04-10): `dc-cr-downtime-mode-e2e` (suite.json line 10750, `activated_release: 20260409-dungeoncrawler-release-h`) and `dc-cr-downtime-mode-playwright` (line 13471) with all 10 TCs (TC-DTM-01 through TC-DTM-10) tagged `feature_id: "dc-cr-downtime-mode"` and `status: pending_implementation`. Permission rules `dc-cr-downtime-mode-activity-acl` and `dc-cr-downtime-mode-gold-log-acl` are already present in `qa-permissions.json`. Suite manifest validates clean (`OK: validated 7 suite manifest(s)`). All entries carry `required_for_release: true` and are correctly tagged. No code changes made; no commits required.

## Next actions
- When Dev implements dc-cr-downtime-mode this release cycle, run targeted playwright regression against TC-DTM-01 through TC-DTM-10 and report PASS/FAIL
- Await dispatch of remaining 134531 unit-test inbox items: dc-cr-feats-ch05, dc-cr-gnome-heritage-sensate, dc-cr-hazards, dc-cr-rest-watch-starvation, dc-cr-skills-society-create-forgery

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 5
- Rationale: Suite was already live; this was a low-effort confirmation check. No blocking issues; Dev can proceed against the existing test plan.
