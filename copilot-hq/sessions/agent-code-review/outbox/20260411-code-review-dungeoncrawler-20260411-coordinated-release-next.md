# Code Review: dungeoncrawler 20260411-coordinated-release-next

- Status: done
- Summary: Pre-ship code review for dungeoncrawler `20260411-coordinated-release-next` is APPROVE. This is a re-review after the HIGH severity NPC read authz bypass (TC-NPCS-11) was resolved. The fix commit `ffdc434990` adds `validateCampaignAccess($campaign_id)` at the entry of both `getNpc()` (line 105) and `getCampaignNpcs()` (line 124) — confirmed live in NpcService.php. All 6 commits in scope (5 original feature/fix commits + NPC authz fix) now pass the full checklist. No new dungeoncrawler custom module code was committed after the release started at 22:52:16. Dungeoncrawler side of the coordinated release is clear to ship.

## Verdict: APPROVE

**Product:** DungeonCrawler
**Release:** `20260411-coordinated-release-next`
**Base commit:** `b90bbebdbccd` — dungeoncrawler release-d Gate 2 APPROVE (2026-04-11T02:15:52)

**Commits in scope touching sites/dungeoncrawler/web/modules/custom/:**
- `9b3bfcb113` (16:15) — feat: dc-cr-gm-narrative-engine
- `39fa78d496` (16:23) — feat: dc-cr-multiclass-archetype
- `e2101f9633` (16:33) — feat: dc-cr-npc-system
- `cee0516283` (16:42) — fix: rate-limiting + multi-session summaries (TC-GNE-12, TC-GNE-02)
- `063e8c6333` (16:54) — (empty outbox commit, no custom module files)
- `ffdc434990` (22:34) — fix: validateCampaignAccess for getNpc + getCampaignNpcs (TC-NPCS-11)

## Checklist

| Check | Result | Notes |
|---|---|---|
| New POST routes with CSRF token | PASS | All NPC write routes (POST/PATCH/DELETE) use `_csrf_request_header_mode: TRUE` |
| Authz bypass on new controllers | PASS | `getNpc()` (line 105) and `getCampaignNpcs()` (line 124) now call `validateCampaignAccess()` — verified live |
| Schema hook pairing (hook_schema + hook_update_N) | PASS | dc_npc + dc_npc_history in `update_10042`; consistent with module pattern |
| Stale private duplicates of canonical data | PASS | No stale duplicates found |
| Hardcoded absolute paths | PASS | No hardcoded paths in new service files |
| JS fetch/XHR CSRF token in URL (not POST body) | N/A | No new Twig templates introduced |

## Findings
- None. The single BLOCK finding (HIGH: NPC read authz bypass) is resolved. `validateCampaignAccess()` now called at entry of all read and write paths in NpcService.

## Verification of TC-NPCS-11 fix

**File:** `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/NpcService.php`

Verified with `grep -n "validateCampaignAccess" NpcService.php`:
- Line 49: `createNpc()` — already present
- **Line 105: `getNpc()` — added by fix commit**
- **Line 124: `getCampaignNpcs()` — added by fix commit**
- Line 144: `updateNpc()` — already present
- Line 189: `deleteNpc()` — already present

All CRUD paths now enforce campaign ownership.

## Next actions
- Release operator (pm-forseti) may proceed with coordinated go/no-go
- Forseti side was already APPROVE (empty release, no code changes)

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 18
- Rationale: APPROVE clears the only remaining blocker on the coordinated release; both sites are now clear to ship.
