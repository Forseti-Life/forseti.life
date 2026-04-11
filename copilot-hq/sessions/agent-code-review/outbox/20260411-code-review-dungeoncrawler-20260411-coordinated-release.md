# Code Review: dungeoncrawler 20260411-coordinated-release

- Status: done
- Summary: Pre-ship code review for dungeoncrawler `20260411-coordinated-release` is **BLOCK**. Four commits are in scope (dc-cr-gm-narrative-engine, dc-cr-multiclass-archetype, dc-cr-npc-system, and the QA-BLOCK fix for rate limiting). The NPC system has an unresolved HIGH severity authorization bypass: `listNpcs` (GET `/api/campaign/{campaign_id}/npcs`) and `getNpc` (GET `/api/campaign/{campaign_id}/npcs/{npc_id}`) both bypass `validateCampaignAccess()`, allowing any authenticated user with `access dungeoncrawler characters` to enumerate NPCs and read NPC details from campaigns they do not own. This was already flagged by QA as TC-NPCS-11 (BLOCK, filed 16:43) with no fix commit in scope. The coordinated-release must not ship until this is resolved.

## Verdict: BLOCK

**Release:** `20260411-coordinated-release`
**Base commit (dungeoncrawler release-e Gate 2 APPROVE):** `b90bbebdbccd` (2026-04-11T02:15:52)
**Commits in scope touching sites/dungeoncrawler/:**
- `9b3bfcb113` (16:15) — feat: dc-cr-gm-narrative-engine
- `39fa78d496` (16:23) — feat: dc-cr-multiclass-archetype
- `e2101f9633` (16:33) — feat: dc-cr-npc-system
- `cee0516283` (16:42) — fix: rate-limiting + multi-session summaries (QA BLOCK resolution for GNE-12 + GNE-02)

## Checklist

| Check | Result | Notes |
|---|---|---|
| New POST routes with CSRF token | PASS | All NPC write routes (POST/PATCH/DELETE) use `_csrf_request_header_mode: TRUE` |
| Authz bypass on new controllers | **BLOCK** | `listNpcs` and `getNpc` GET routes missing campaign ownership guard — see Finding 1 |
| Schema hook pairing (hook_schema + hook_update_N) | PASS | dc_npc + dc_npc_history created in `update_10042`; consistent with module pattern |
| Stale private duplicates of canonical data | PASS | No stale duplicates found |
| Hardcoded absolute paths | PASS | No hardcoded paths in new service files |
| JS fetch/XHR CSRF token in URL (not POST body) | N/A | No new Twig templates introduced |

## Findings

### Finding 1 — HIGH: NPC read endpoints bypass campaign ownership guard

**File:** `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/NpcService.php`
**Lines:** `getCampaignNpcs()` (line 122) and `getNpc()` (line 104)

**Problem:** Both methods query `dc_npc` filtered by `campaign_id` only, without calling `validateCampaignAccess()`. Any authenticated user with the `access dungeoncrawler characters` permission can:
- Enumerate all NPCs in any campaign by calling GET `/api/campaign/{any_id}/npcs`
- Read individual NPCs from any campaign by calling GET `/api/campaign/{any_id}/npcs/{npc_id}`

**Note:** This is the same issue QA flagged as TC-NPCS-11 (BLOCK filed 16:43:51, commit `78fce300e8`). No fix commit exists.

**Recommended fix:**
```php
// In getCampaignNpcs() and getNpc(), add at the top of the method:
$this->validateCampaignAccess($campaign_id);
```
This aligns with `createNpc()`, `updateNpc()`, and `deleteNpc()` which all call `validateCampaignAccess()`.

### Finding 2 — LOW: AiGmService rate-limit fix looks correct

`enforceRateLimit()` added at lines 133+; called before all `invokeModelDirect` paths (lines 263, 329, 681). `KeyValueExpirableFactoryInterface` properly injected as 10th DI arg. No issues.

### Finding 3 — LOW: MulticlassArchetypeService — constants only, no new routes

`MULTICLASS_ARCHETYPES` constant and `MulticlassArchetypeService` are pure data/logic with no new routes or DB writes. No security concerns.

## Next actions
- Dispatch HIGH finding fix to dev-dungeoncrawler: add `validateCampaignAccess()` calls in `getCampaignNpcs()` and `getNpc()` in `NpcService.php`
- CEO: dispatch re-review inbox item for agent-code-review after fix is committed (per BLOCK → re-review protocol)

## Blockers
- None for the review itself

## Needs from CEO
- N/A

## ROI estimate
- ROI: 18
- Rationale: Unresolved campaign ownership bypass on NPC read endpoints is a real authz vulnerability that would expose user data in production. Blocking the release protects active user campaigns from unauthorized enumeration.
