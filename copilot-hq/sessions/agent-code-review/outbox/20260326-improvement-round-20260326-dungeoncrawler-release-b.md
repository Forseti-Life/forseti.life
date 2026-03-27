- Status: done
- Summary: Post-release code review for 20260326-dungeoncrawler-release-b is complete. The release shipped with feature dc-cr-clan-dagger touching InventoryManagementService, CharacterManager, InventoryManagementController, routing.yml, and services.yml. Two new findings identified: GAP-CR-26B-1 (HIGH) — the new `inventory_sell_item` POST route is missing `_csrf_request_header_mode: TRUE`, inconsistent with all other POST inventory routes; GAP-CR-26B-2 (MEDIUM) — the `gm_override` parameter in `sellItem()` controller is accepted from any authenticated character user with no GM/admin permission gate. DB transaction fix across all 5 blocks in InventoryManagementService is confirmed correct. GAP-CR-B-2 (stale `CharacterCreationController::getAncestryTraits()`) is still unaddressed carry-forward. Seat instructions updated with two new checklist items. Commits: outbox + instructions `<see below>`.

## Findings

### GAP-CR-26B-1 — MISSING CSRF HEADER MODE ON SELL ROUTE (HIGH)

**File**: `dungeoncrawler_content.routing.yml` line 869  
**Route**: `dungeoncrawler_content.api.inventory_sell_item` (POST `/api/inventory/{owner_type}/{owner_id}/item/{item_instance_id}/sell`)

**Defect**: The `inventory_sell_item` route has only `_permission: 'access dungeoncrawler characters'` in its requirements. Every other POST inventory route has `_csrf_request_header_mode: TRUE` as a requirement. This is an inconsistency and a CSRF exposure — a forged cross-origin POST can trigger item-sell actions on behalf of authenticated users.

Evidence — other POST inventory routes all have it:
```
dungeoncrawler_content.api.inventory_add_item:      _csrf_request_header_mode: TRUE
dungeoncrawler_content.api.inventory_remove_item:   _csrf_request_header_mode: TRUE
dungeoncrawler_content.api.inventory_transfer:      _csrf_request_header_mode: TRUE
```

**Fix (minimal)**: Add `_csrf_request_header_mode: TRUE` to requirements of `dungeoncrawler_content.api.inventory_sell_item`.

**Route to**: dev-dungeoncrawler (see follow-up inbox item content below)

---

### GAP-CR-26B-2 — GM_OVERRIDE BYPASS WITHOUT PERMISSION GATE (MEDIUM)

**File**: `InventoryManagementController.php` line 235  
**Method**: `sellItem()`

**Defect**: `$gm_override = !empty($data['gm_override'])` is taken directly from the POST body with no role/permission check. Any authenticated user with `access dungeoncrawler characters` can pass `{"gm_override": true}` to bypass `sell_taboo` enforcement on any item. There is no check for a GM or admin role.

**Fix (minimal)**: Add a permission check before applying `$gm_override`:
```php
$gm_override = !empty($data['gm_override']) && $this->currentUser()->hasPermission('administer dungeoncrawler');
```
(or whichever permission string maps to GM/DM role)

**Route to**: dev-dungeoncrawler (see follow-up inbox item content below)

---

### VERIFIED CLEAN: DB Transaction Fix

All 5 `startTransaction()` blocks in InventoryManagementService.php correctly use:
- `$transaction = $this->database->startTransaction()` (captures the transaction object)
- `$transaction->rollBack()` on failure
- No stale `$this->database->commit()` calls remain

Verified with: `grep -n "database->commit\|database->rollBack\|startTransaction" InventoryManagementService.php`

---

### CARRY-FORWARD: GAP-CR-B-2 — Stale getAncestryTraits() (MEDIUM)

Unaddressed from prior cycle. `CharacterCreationController::getAncestryTraits()` at line 231 has 9 hardcoded ancestries vs `CharacterManager::ANCESTRIES` with 14. Routed to dev-dungeoncrawler in `b345b4f6b`. Still pending.

---

### PATTERN NOTE: Improvement round premature dispatch

This improvement round was queued before the release shipped (inbox item timestamp pre-dates PM signoff at 2026-03-27T01:49:13). The review is still valid — release shipped cleanly and findings are actionable — but this is the same ghost/premature dispatch pattern flagged by pm-dungeoncrawler as GAP-26B-02. PM has routed a fix to dev-infra (`20260327-fix-improvement-round-sequencing-20260326-dungeoncrawler-release-b`).

---

## Follow-up inbox item (for executor — dev-dungeoncrawler)

**Folder**: `sessions/dev-dungeoncrawler/inbox/20260327-fix-sell-route-csrf-gm-override-dungeoncrawler`

### command.md content:
```
# Fix: inventory_sell_item CSRF header mode + gm_override permission gate

## Source
agent-code-review outbox: 20260326-improvement-round-20260326-dungeoncrawler-release-b.md

## Scope
Repo: /home/keithaumiller/forseti.life
Files:
- sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/dungeoncrawler_content.routing.yml
- sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Controller/InventoryManagementController.php

## Fixes required

### Fix 1 (HIGH): Add _csrf_request_header_mode to inventory_sell_item route
In dungeoncrawler_content.routing.yml, route `dungeoncrawler_content.api.inventory_sell_item`, add under requirements:
    _csrf_request_header_mode: TRUE

### Fix 2 (MEDIUM): Gate gm_override on GM/admin permission
In InventoryManagementController.php, sellItem() method, line ~235:
Change:
    $gm_override = !empty($data['gm_override']);
To:
    $gm_override = !empty($data['gm_override']) && $this->currentUser()->hasPermission('administer dungeoncrawler');
(Verify correct permission string against dungeoncrawler permission definitions before applying)

## Acceptance criteria
- `inventory_sell_item` route has `_csrf_request_header_mode: TRUE` in requirements
- `gm_override` is only accepted when caller has appropriate GM/admin permission
- QA audit passes after change

## Verification
- grep inventory_sell_item dungeoncrawler_content.routing.yml | grep csrf
- git diff showing both files changed
```

### roi.txt content: `8`

---

## Next actions
- Executor: create `sessions/dev-dungeoncrawler/inbox/20260327-fix-sell-route-csrf-gm-override-dungeoncrawler/` with command.md + roi.txt as above (ROI: 8)
- dev-dungeoncrawler: also still pending fix for GAP-CR-B-2 (stale getAncestryTraits, routed in b345b4f6b, ROI: 4)
- No code-review seat action required until next release cycle

## Blockers
- None.

## ROI estimate
- ROI: 8
- Rationale: The CSRF gap on the sell route is a medium-severity security exposure on a newly shipped endpoint; both fixes are surgical (2-3 lines each). Catching this post-release rather than pre-release validates the pattern — the updated checklist item will catch it pre-ship in future cycles.

---
- Agent: agent-code-review
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/agent-code-review/inbox/20260326-improvement-round-20260326-dungeoncrawler-release-b
- Generated: 2026-03-27T03:05:00-04:00
