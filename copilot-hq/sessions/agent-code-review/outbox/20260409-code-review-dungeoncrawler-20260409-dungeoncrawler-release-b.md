- Status: done
- Summary: Pre-ship code review for `20260409-dungeoncrawler-release-b`. Cutoff: `aa2052e6a..HEAD` (28 commits, 19 files). Data-only fast-path does NOT apply — this release includes new controllers, routing, schema hooks, and service files. Schema hook pairing: dc_sessions (update_10037 + hook_schema ✓), combat_afflictions (update_10036 + hook_schema ✓), dc_requirements.feature_id (update_10038 idempotent + hook_schema ✓). CSRF: three new POST routes (session_start, session_end, campaign_invite) all carry `_csrf_request_header_mode: TRUE` ✓. All GET routes correctly omit CSRF ✓. `_campaign_access` custom check is registered in services.yml with `applies_to: _campaign_access` ✓. One MEDIUM finding (qa-permissions.json not updated), one LOW finding (stale doc-comment method mismatch). No CRITICAL or HIGH findings. **APPROVE with caveats — see findings table.**

## Findings

| # | Severity | File | Finding | Recommendation |
|---|---|---|---|---|
| 1 | MEDIUM | `org-chart/sites/dungeoncrawler/qa-permissions.json` | 13 new routes (`/api/sessions/*`, `/api/dc/*`, `/api/campaign/*/play-sessions*`, `/api/campaign/*/xp-total/*`, `/api/campaign/*/invite`) not registered in qa-permissions.json. Rule: new routes must be pre-registered in same commit as routing.yml to prevent false-positive QA blocks. | QA preflight for release-b must add entries for all 13 new routes before Gate 2. Dev or QA to update qa-permissions.json as release-b preflight. |
| 2 | LOW | `PlaySessionController.php` line 28 | Class doc comment header lists `POST /api/campaign/{campaign_id}/xp-total/{character_id}` but routing.yml and method-level comment both correctly declare it as `GET`. Stale doc comment only; no functional impact. | Fix doc comment to `GET` in same PR as any next touch to this file. |

## Checklist results

- [x] CSRF on new POST routes — all 3 POST routes carry `_csrf_request_header_mode: TRUE` ✓
- [x] Route method enforcement — all routes use `methods: [GET]` or `methods: [POST]` at route level ✓
- [x] Schema hook pairing — dc_sessions/combat_afflictions/dc_requirements all have both hook_schema() and hook_update_N() ✓
- [x] VALID_TYPES pairing — `snare` in VALID_TYPES with snare items present in same release ✓
- [x] Authorization bypass — no gm_override or admin_override bypass flags found ✓
- [x] `_campaign_access` custom check registered — `applies_to: _campaign_access` in services.yml ✓
- [x] Stale private duplicates — DcAdjustmentService/RecallKnowledgeService use their own lookup tables, not re-implementing CharacterManager constants ✓
- [x] Hardcoded paths — none found in new service/controller files ✓
- [x] ai_conversation fork parity — no changes to ai_conversation in this release, no regression risk ✓
- [ ] qa-permissions.json pre-registration — **MISSING** for 13 new routes (Finding #1, MEDIUM)

## Verdict

**APPROVE** — no CRITICAL or HIGH findings. One MEDIUM (qa-permissions pre-registration) must be resolved as part of QA preflight before Gate 2 passes. Low doc comment mismatch is non-blocking.

## Next actions
- QA preflight must register all 13 new `/api/sessions/`, `/api/dc/`, `/api/campaign/*/play-sessions*`, `/api/campaign/*/xp-total/*`, `/api/campaign/*/invite` routes in `org-chart/sites/dungeoncrawler/qa-permissions.json` before Gate 2 — route registrations should classify each as `_permission: 'access dungeoncrawler characters'` (DC API, session GET routes) or `_campaign_access: TRUE` (campaign-scoped routes)

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 11
- Rationale: Release-b is the active cycle; unblocking Gate 2 requires clean code review. Missing qa-permissions pre-registration is a recurring pattern that must be caught before QA rather than during, preventing a wasted QA iteration.
