# Code Review: dungeoncrawler 20260412-dungeoncrawler-release-e

- Status: done
- Summary: Pre-ship code review for dungeoncrawler `20260412-dungeoncrawler-release-e` is APPROVE WITH FINDINGS. Release window started `2026-04-12T17:34:58+00:00`. One commit in scope (`b9dd1c47a`) contains 5 DC custom module files: new `RoadmapPipelineStatusResolver` service, updated `RoadmapController`, `services.yml` registration, Twig template doc text, and unit tests. Two findings: FINDING-01 MEDIUM (path traversal via unsanitized `feature_id` in filesystem path construction) and FINDING-02 LOW (hardcoded default features path undocumented for non-standard environments). Neither is a blocker; the feature degrades gracefully (falls back to DB status). Dev inbox item dispatched for FINDING-01. NOTE: the prior outbox for this review was factually incorrect (claimed 0 DC module changes) — this is the corrected review.

## Verdict: APPROVE

**Product:** dungeoncrawler
**Release:** `20260412-dungeoncrawler-release-e`
**Release window start:** `2026-04-12T17:34:58+00:00`
**Commits in scope:** `b9dd1c47a` (Auto checkpoint: 2026-04-12T17:55:42+00:00)

## Commits reviewed

| Commit | Description | Files (DC custom) |
|---|---|---|
| `b9dd1c47a` | Auto checkpoint — includes DC module changes | 5 files |

**DC custom files changed:**
- `dungeoncrawler_content.services.yml` — registered `dungeoncrawler_content.roadmap_pipeline_status_resolver`
- `src/Controller/RoadmapController.php` — injected `RoadmapPipelineStatusResolver`, status now resolved via pipeline
- `src/Service/RoadmapPipelineStatusResolver.php` — new service (reads `feature.md` files from HQ features directory)
- `templates/dungeoncrawler-roadmap.html.twig` — added explanatory subtitle text
- `tests/src/Unit/Service/RoadmapPipelineStatusResolverTest.php` — unit tests for resolver (3 test methods)

## Checklist

| Check | Result | Notes |
|---|---|---|
| New POST routes with CSRF | PASS | No new routes; existing `/roadmap` is GET-only with `_access: TRUE` |
| Authorization bypass | PASS | Read-only page; status resolver is output-side only, no write path |
| Schema hook pairing | PASS | No schema changes |
| VALID_TYPES pairing | PASS | No new equipment/item types |
| Stale private duplicates | PASS | `PIPELINE_TO_ROADMAP` map is site-local; no canonical source duplicated |
| Hardcoded absolute paths | FINDING-02 LOW | Default path `/home/ubuntu/forseti.life/copilot-hq/features` — see below |
| qa-permissions.json pairing | PASS | No new permissions introduced |
| Service DI wiring | PASS | `services.yml` registered, `create()` factory correct, constructor stores injection |
| Unit tests present | PASS | 3 test cases covering pipeline resolution, fallback, and ready/deferred mapping |

## Findings

### FINDING-01 MEDIUM — Path traversal via unsanitized `feature_id`

**File:** `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/RoadmapPipelineStatusResolver.php:72`

**Code:**
```php
$feature_path = $this->featuresPath . DIRECTORY_SEPARATOR . $feature_id . DIRECTORY_SEPARATOR . 'feature.md';
```

**Issue:** `feature_id` is read from the DB and passed directly into a filesystem path without any sanitization. A value like `../../../etc/passwd` would traverse outside the features directory. The practical attack surface is limited (only admin/drush can set `feature_id`, output is capped to a regex-matched status string, `/feature.md` is appended), but defense-in-depth requires sanitization of filesystem inputs regardless of origin.

**Fix:**
```php
// At top of getPipelineStatus(), before path construction:
if ($feature_id === '' || str_contains($feature_id, '/') || str_contains($feature_id, '\\') || str_contains($feature_id, '..')) {
  $this->statusCache[$feature_id] = NULL;
  return NULL;
}
```

**Dispatch:** dev-dungeoncrawler inbox item created.

---

### FINDING-02 LOW — Undocumented required `settings.php` key

**File:** `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/RoadmapPipelineStatusResolver.php:43`

**Issue:** The service defaults to `/home/ubuntu/forseti.life/copilot-hq/features` — a production-server-specific absolute path. The `Settings::get('dungeoncrawler_pipeline_features_path', ...)` override exists and is correct, but no `settings.php.example` entry or site.instructions documentation exists to tell operators they must set this on staging/new environments. Without it, the resolver silently falls back to DB status everywhere — not a crash, but a silent correctness gap.

**Fix:** Add entry to `sites/dungeoncrawler/web/sites/default/settings.php.example` (or site.instructions.md):
```php
// Required for dungeoncrawler RoadmapPipelineStatusResolver.
// $settings['dungeoncrawler_pipeline_features_path'] = '/path/to/copilot-hq/features';
```

No dispatch needed; this is a docs-only low-urgency item.

## Next actions
- Release operator may proceed with dungeoncrawler release-e gate signoff.
- `dev-dungeoncrawler`: fix FINDING-01 MEDIUM (path traversal) in next available release.
- `dev-dungeoncrawler`: also address outstanding FINDING-01 HIGH from release-d (`gm_override` authz bypass in `InventoryManagementController`) — still unresolved.

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 7
- Rationale: Path traversal in a filesystem-reading service warrants a follow-through fix before more feature_id values accumulate in the DB; combined with the existing HIGH finding from release-d, dev-dungeoncrawler has two queued security items.

---
- Agent: agent-code-review
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/agent-code-review/inbox/20260412-code-review-dungeoncrawler-20260412-dungeoncrawler-release-e
- Generated: 2026-04-12T18:30:00+00:00
- Corrects: prior outbox that incorrectly reported 0 DC module changes in this release window
