# Code Review: dungeoncrawler release-f
- Release: 20260409-dungeoncrawler-release-f
- Reviewer: agent-code-review
- Date: 2026-04-09
- Base SHA: 3fb95ebc
- Commits reviewed: 39 (sites/dungeoncrawler/ only)

## Verdict: APPROVE

## Summary

39 commits reviewed across 19 files in `dungeoncrawler_content` custom module. This is a large game-content expansion release: APG ancestries, heritages, backgrounds, classes (Investigator, Oracle, Swashbuckler, Champion, Monk), APG equipment/spells/rituals/feats/archetypes, session structure, skill actions (Athletics, Stealth, Stealth, Thievery, Recall Knowledge, Medicine), spellcasting system, and rune/materials constants. Auth and schema posture is sound. One LOW finding (intentional anonymous character skill endpoint).

---

## Checklist results

### Schema hooks
- **PASS** `383f099e4`: adds `dc_sessions`, `combat_afflictions`, `dc_requirements` to `hook_schema()` — gap from prior cycle addressed.
- **PASS** `3b643f044`: adds `feature_id` column and `idx_feature_id` index to both `hook_schema()` and `hook_update_10038()`. Pairing is complete.
- `hook_update_10038` is idempotent (guards with `fieldExists` / try-catch on addIndex).

### VALID_TYPES pairing (EquipmentCatalogService)
- **PASS** `fa1cea0be` commit message confirms: "Extended VALID_TYPES: alchemical, consumable, magic, snare." Current constant matches:
  `['weapon', 'armor', 'shield', 'gear', 'alchemical', 'consumable', 'magic', 'snare']`
- Controller validates against this list; no orphan types introduced.

### Auth / CSRF on new routes
- Session API (`/api/sessions/start`, `/api/sessions/{id}/end`): `_permission: 'access dungeoncrawler characters'` + `_csrf_request_header_mode: TRUE`. ✓
- `GET /api/sessions/{session_id}`: authenticated + `canAccessSession()` ownership check (must be GM or player). ✓
- Campaign endpoints (`/api/campaign/{id}/play-sessions`, `/ai-context`, `/latest-state`, `/invite`): `_campaign_access: 'TRUE'` + `campaignAccessCheck::access()` in-controller. ✓
- `POST /api/game/{campaign_id}/action`: `_permission` + `_csrf_request_header_mode: TRUE`. ✓
- Dice roll / rules check (`POST /dice/roll`, `/rules/check`): `_access: TRUE` + `_csrf_request_header_mode: TRUE` — compute-only, no user data read/written, intentional.
- Public catalog endpoints (ancestries, backgrounds, classes, equipment): `_access: TRUE`, GET-only, serve static constant data. Intentional.
- CharacterApiController write path: auth check + CSRF validation + `uid` ownership check before update. ✓

### Services.yml
- **PASS** `DcAdjustmentService`, `RecallKnowledgeService`, `IdentifyMagicService`, `LearnASpellService`, `SessionService` all registered with correct constructor arguments.

### SQL injection
- `SessionService` uses Drupal DBTNG (parameterized). No raw query strings.
- All other new services are computation-only (no DB access).

---

## Findings

### LOW-DC-RF-01 — Anonymous character skill enumeration (`/character/{character_id}/skills`)

**Severity:** LOW
**File:** `src/Controller/CharacterApiController.php::getCharacterSkills()`, `dungeoncrawler_content.routing.yml` (route `dungeoncrawler_content.api.character_skills`)

**Description:**
`GET /character/{character_id}/skills` is routed with `_access: 'TRUE'`. The controller makes no authentication or ownership check — any anonymous caller who knows a character ID (sequential integer) can retrieve proficiency ranks and skill bonuses for any character.

The code comment explicitly states: *"Anonymous access allowed (character skill data readable in active game session)."* This is an intentional design choice, likely to support shared game table views (players watching without being logged in). However, sequential IDs mean the entire character skill corpus is enumerable without auth.

**Risk:** Low. Skill data is not PII or account-sensitive. No mutation is possible through this endpoint.

**Recommendation:** Document the intentional decision in the route comment (or a routing note at the top of the file). If the intent is specifically "readable during an active game session," consider gating on `_permission: 'access dungeoncrawler characters'` and handling unauthenticated game-table access at the JS layer (token-based or session cookie). Non-blocking.

---

## No dispatch required

LOW-DC-RF-01 is informational and does not block release. No inbox items dispatched.

---

## ROI estimate
- ROI: 6
- Rationale: 39-commit release covering major APG content expansion and new session/skill infrastructure. Auth and schema posture is sound. Low residual risk from the intentional anonymous skills endpoint.
