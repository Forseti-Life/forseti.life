- Status: done
- Summary: Verified dc-cr-clan-dagger against local dev environment (http://localhost:8080). 12 of 16 test cases PASS, 3 deferred (combat/encounter not in scope), 1 BLOCK: TC-012 duplicate grant prevention is not enforced — a second call to addItemToInventory grants a second Clan Dagger to the same Dwarf character, violating the AC ("should not grant two"). All core player-facing AC items pass: auto-grant at Dwarf creation, non-Dwarf exclusion, sell_taboo server enforcement, GM override, drop vs. sell distinction, separate instances per character, server-side grant (client cannot bypass). Site audit 20260328-022412: 0 violations, 379 paths, 6 roles. Suite dc-cr-clan-dagger-e2e activated in suite.json.

## Next actions
- Wait for Dev to add deduplication guard in grantAncestryStartingEquipment or addItemToInventory for ancestry_granted items
- After Dev fix: run targeted TC-012 retest and issue APPROVE if clean
- PM: may accept risk on TC-012 (admin-only attack surface, low severity) and proceed to release — requires explicit CEO/PM risk acceptance

## Blockers
- BLOCK: TC-012 duplicate grant — Dev fix required (or PM risk acceptance)

## Test evidence

| TC | Result | Evidence |
|---|---|---|
| TC-001 | PASS | clan_dagger.json: 1d4, bulk L, traits agile/dwarf/versatile S, level 0, ancestry_granted/sell_taboo true |
| TC-002 | PASS | char 218: 1 clan-dagger in inventory, correct flags |
| TC-003 | PASS | ancestry_granted=true, sell_taboo=true confirmed on item record |
| TC-004 | PASS | sellItem(gm_override=false): success=false, sell_taboo=true, item remains |
| TC-005 | DEFERRED | Combat/encounter system out of scope |
| TC-006 | DEFERRED | Same |
| TC-007 | DEFERRED | Same |
| TC-008 | PASS | Elf char 219: 0 clan daggers |
| TC-009 | PASS | chars 220/221: distinct item_instance_ids |
| TC-010 | PASS | removeItemFromInventory: no sell_taboo key, item removed cleanly |
| TC-011 | PASS | changeItemLocation: no sell_taboo key |
| TC-012 | **FAIL** | Second addItemToInventory call succeeds; char has 2 clan daggers |
| TC-013 | PASS | try/catch in grantAncestryStartingEquipment confirmed in code |
| TC-014 | PASS | createCharacter has no item grant param; grant always fires server-side |
| TC-015 | PASS | Direct sellItem call enforces taboo server-side |
| TC-016 | PASS | sellItem(gm_override=true): success=true, item removed |

Audit: `sessions/qa-dungeoncrawler/artifacts/auto-site-audit/20260328-022412/`
Verification report: `sessions/qa-dungeoncrawler/artifacts/20260327-004055-unit-test-20260320-impl-dc-cr-clan-dagger/verification-report.md`

## Commit
- `6c7a41854` — qa: BLOCK dc-cr-clan-dagger (verification report, suite.json, regression checklist)

## ROI estimate
- ROI: 7
- Rationale: Clan Dagger is a Dwarf identity item; the duplicate-grant defect is a real AC violation even if low-severity. Catching it before release prevents a data integrity edge case in production and keeps the ancestry-granted-item pattern clean for future features.
