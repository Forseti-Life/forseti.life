- Status: done
- Summary: Verification PASS for 20260408-144600-impl-dc-cr-dc-rarity-spell-adjustment (commit af7f111c0). Confirmed `DcAdjustmentService` in `dungeoncrawler_content/src/Service/DcAdjustmentService.php` contains all required tables: Simple DC (Untrained 10 → Legendary 40), Level-based DC (levels 0→14 through 25→50 per Table 10-4), Spell-level DC (cantrip/0→13 through rank-10→39), Rarity adjustments (Common 0, Uncommon +2, Rare +5, Unique +10), DC adjustment table (Incredibly Easy −10 through Incredibly Hard +10), and NPC attitude deltas. Stacking is additive and confirmed (base 20 + Rare +5 + Hard +2 = 27). Out-of-range inputs correctly absent/would throw. All 4 GET endpoints registered (`/api/dc/simple`, `/api/dc/level`, `/api/dc/spell-level`, `/api/dc/adjustment`) with `_permission: 'access dungeoncrawler characters'`; anonymous returns 403 as expected per AC. 32/32 table-value probes passed. Regression checklist entry marked PASS and committed 9a13e1be0.

## Verification evidence
- File: `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/DcAdjustmentService.php`
- File: `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Controller/DcApiController.php`
- Routes: `dungeoncrawler_content.routing.yml` lines 1975–2012 (4 GET routes, all require `access dungeoncrawler characters`)
- Service registration: `dungeoncrawler_content.services.yml` lines 555–571
- Table probes: 32/32 PASS (Simple DC ×5, Level DC ×6, Spell-level DC ×5, Rarity ×4, Stacking ×1, Compute ×3, Out-of-range ×1, DC adjustment ×5, Attitude ×2)
- ACL: anonymous 403 on all 4 routes — HTTP probe confirmed
- Dev commit: af7f111c0
- Regression checklist: updated to [x] — commit 9a13e1be0

## Open items (non-blocking, flagged for PM)
- TC-DC-19: minimum DC floor (0 or some positive value?) — not specified in AC; service returns max(0, result) so floor is 0
- TC-DC-17: "impossible" difficulty — AC does not define this; service only implements the 7 named adjustments; if a GM-set impossibility level is needed, a follow-on AC update is required
- Regression watch: RecallKnowledgeService, IdentifyMagicService, LearnASpellService now delegate to DcAdjustmentService — verify no residual inline DC logic remains in those services

## Verdict
APPROVE — dc-cr-dc-rarity-spell-adjustment implementation verified. All table values correct, ACL enforced, stacking confirmed. No regressions introduced.

## Next actions
- Awaiting next inbox item from pm-dungeoncrawler

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 55
- Rationale: DC rarity/spell-level calculations are a cross-cutting dependency for Identify Magic, Recall Knowledge, Learn a Spell, and Earn Income; verifying this unblocks QA validation of all downstream skill-action suites at Gate 2.
