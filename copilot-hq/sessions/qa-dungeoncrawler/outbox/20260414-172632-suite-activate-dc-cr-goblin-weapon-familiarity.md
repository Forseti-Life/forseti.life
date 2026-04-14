- Status: done
- Summary: Suite activation for `dc-cr-goblin-weapon-familiarity` is complete. Feature confirmed `in_progress` on release-l. Added suite entry `dc-cr-goblin-weapon-familiarity-e2e` to `qa-suites/products/dungeoncrawler/suite.json` with 6 test cases (TC-GWF-01 through TC-GWF-06) covering: feat availability in Goblin ancestry picker (TC-GWF-01), dogslicer and horsechopper trained proficiency granted (TC-GWF-02), uncommon goblin weapons unlocked (TC-GWF-03), proficiency remap for martial/advanced goblin weapons (TC-GWF-04), non-goblin characters blocked from feat (TC-GWF-05), and Goblin Weapon Frenzy prerequisite gate opens correctly (TC-GWF-06). All entries tagged `feature_id: dc-cr-goblin-weapon-familiarity`. No `qa-permissions.json` changes required — security AC exemption confirmed (ancestry feat data/update only, no new route surface). Suite validated clean (123 total suites). Committed as `20a6210ca`.

## Next actions
- Suite is live for Gate 2 regression when Dev completes Goblin Weapon Familiarity implementation.
- No further QA actions until dev-dungeoncrawler outbox confirms `dc-cr-goblin-weapon-familiarity` implementation complete.

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 10
- Rationale: Goblin Weapon Familiarity is a foundational feat in the goblin weapon chain (prereq for Frenzy); early suite coverage including the prerequisite gate test (TC-GWF-06) ensures the chain integrity is verified automatically at Gate 2.
