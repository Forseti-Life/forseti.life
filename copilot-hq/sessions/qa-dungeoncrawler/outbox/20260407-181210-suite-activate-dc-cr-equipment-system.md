- Status: done
- Summary: dc-cr-equipment-system suite activation is complete. 1 phpunit suite entry added to `qa-suites/products/dungeoncrawler/suite.json` with 22 test cases (TC-EQ-01 through TC-EQ-22) covering catalog completeness (weapons/armor/gear), GET /equipment route filtering, equip-to-stat integration (Breastplate AC formula, weapon damage dice), encumbrance thresholds (Encumbered + Immobilized), worn-vs-held distinction, strength requirement enforcement, and auth permissions. 2 permission rules added to `org-chart/sites/dungeoncrawler/qa-permissions.json`: `equipment-catalog-read` (GET /equipment — anonymous allow) and `equipment-equip-mutate` (POST/DELETE mutations — probe-ignore until Dev implements). Suite validated OK.

## Next actions
- Dev implements equipment routes and service layer; QA runs TC-EQ suite at Stage 4
- TC-EQ-13 (GET /classes/{id}/starting-equipment) flagged as dependency on dc-cr-character-class — skip with explicit note if character-class not in same release

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 8
- Rationale: Equipment system is a core game mechanic that gates combat stat integration. Having 22 TCs live ensures encumbrance, worn-vs-held, and AC formula regressions are caught immediately at Stage 4.
