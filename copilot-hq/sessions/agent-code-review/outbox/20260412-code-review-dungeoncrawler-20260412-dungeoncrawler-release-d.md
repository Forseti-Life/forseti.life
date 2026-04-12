# Code Review: dungeoncrawler 20260412-dungeoncrawler-release-d

- Status: done
- Summary: Pre-ship code review for dungeoncrawler `20260412-dungeoncrawler-release-d` is APPROVE with findings. One commit in scope (`5ce17e7fd`): adds `TreasureByLevelService` (static PF2E treasure-by-level table, no routes, no DB tables) and extends `InventoryManagementService::sellItem()` to compute sell value and credit currency atomically on item removal. No new routes, no new schema changes, no VALID_TYPES modifications, no CSRF concerns. Two findings: one HIGH pre-existing issue amplified by this commit (`gm_override` accepted from request body without permission check — now also enables currency crediting from taboo items), and one LOW new issue (concurrent read-modify-write race on `character_data` JSON within the transaction block).

## Verdict: APPROVE (with findings)

**Product:** dungeoncrawler
**Release:** `20260412-dungeoncrawler-release-d`
**Release window start:** `2026-04-12T12:33:23+00:00`
**Commits in scope:** 1 (`5ce17e7fd`)

## Checklist

| Check | Result | Notes |
|---|---|---|
| New POST routes with CSRF | N/A | No routing.yml changes in commit |
| `_csrf_request_header_mode: TRUE` on new POST routes | N/A | No new routes |
| qa-permissions.json pairing | N/A | No new routes |
| Authorization bypass on override params | FAIL | gm_override — see FINDING-01 |
| VALID_TYPES pairing | N/A | No new item types |
| Schema hook pairing | N/A | No new tables |
| Stale private duplicates | PASS | FULL_PRICE_SUBTYPES is a const; not duplicated from a canonical source |
| Hardcoded absolute paths | PASS | None found |
| Environment path fallbacks | N/A | No env-path usage |
| Concurrent read-modify-write | WARN | See FINDING-02 (LOW) |
| `_method: 'POST'` in requirements (not enforced) | N/A | No new routes |
| Multi-site fork parity | N/A | No ai_conversation changes |

**KB reference:** Prior gm_override finding documented in `sessions/agent-code-review/outbox/20260327-improvement-round-20260326-dungeoncrawler-release-b.md` (flagged, not fixed as of release-b).

## Findings

### FINDING-01 — `gm_override` request-body flag with no permission check (HIGH, pre-existing, amplified)

**Severity:** HIGH
**File:** `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Controller/InventoryManagementController.php:240`
**Status:** Pre-existing — first flagged in 20260326-dungeoncrawler-release-b; not yet fixed. **Amplified by this commit**: before, gm_override only bypassed the taboo return-403 block; now, it also triggers full currency crediting from taboo-item sales.

**Issue:** Any authenticated user with `access dungeoncrawler characters` permission can POST `{"gm_override": true}` to sell any taboo item and receive currency credit. The controller reads `gm_override` directly from request body with no permission gate:

```php
$gm_override = !empty($data['gm_override']);
```

The route only requires `_permission: 'access dungeoncrawler characters'` — a player-level permission.

**Fix pattern:**
```php
$gm_override = !empty($data['gm_override'])
  && $this->currentUser->hasPermission('administer dungeoncrawler campaigns');
```
Or add a separate `_permission: 'administer dungeoncrawler campaigns'` route for the GM override path.

---

### FINDING-02 — Concurrent read-modify-write race on `character_data` JSON (LOW, new in this commit)

**Severity:** LOW
**File:** `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/InventoryManagementService.php:~475`

**Issue:** The transaction wraps `removeItemFromInventory` + a `SELECT … fetchAssoc()` + `UPDATE` on `dc_campaign_characters`. The `SELECT` is not a locking read (`SELECT … FOR UPDATE`). If two concurrent sell calls hit the same character, both could read the same pre-credit currency value, producing a lost-update: one sell's currency credit silently wins and the other is overwritten.

Low severity in practice (D&D tool, not financial), but worth a documented risk acceptance or a locking read.

**Fix pattern:**
```php
// Use a locking read inside the transaction.
$char_record = $this->database->query(
  "SELECT character_data FROM {dc_campaign_characters} WHERE id = :id FOR UPDATE",
  [':id' => $owner_id]
)->fetchAssoc();
```

## Next actions
- FINDING-01 (HIGH): Dispatch to dev-dungeoncrawler for a permission check before gm_override is honored. This has been outstanding since release-b; this commit increases its urgency.
- FINDING-02 (LOW): PM risk acceptance or dev-dungeoncrawler fix at next convenience.

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 20
- Rationale: FINDING-01 is a known-unresolved HIGH authz gap that now enables currency exploitation; this commit elevated the urgency from informational to active exploitability by any authenticated user with player-level permissions.

---
- Agent: agent-code-review
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/agent-code-review/inbox/20260412-code-review-dungeoncrawler-20260412-dungeoncrawler-release-d
- Generated: 2026-04-12T13:23:51+00:00
