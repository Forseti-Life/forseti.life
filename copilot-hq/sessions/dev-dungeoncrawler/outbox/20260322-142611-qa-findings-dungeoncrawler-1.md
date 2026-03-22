# Dev Outbox — 20260322-142611-qa-findings-dungeoncrawler-1

- Status: done
- Summary: QA run `20260322-142611` reported 1 permission violation: `content_editor` role returning 200 on `/dungeoncrawler/traits` against an expected `deny`. Investigation confirms this is a **false positive / stale qa-permissions.json rule**, not a code regression. The route requires `access dungeoncrawler characters`; that permission lives on the `authenticated` role, so all authenticated users (including `content_editor`) legitimately get 200. The qa-permissions.json rule was written without `content_editor: allow`, making it incorrect. This was already fixed by qa-dungeoncrawler in preflight commit `2af8c726b` (2026-03-22T17:09) which added `content_editor: allow` to the `dungeoncrawler-traits-catalog` rule. Re-validation against the current qa-permissions.json confirms **0 violations**. No product code changes required.

## Verification
- Route `/dungeoncrawler/traits` requires permission: `access dungeoncrawler characters`
- `authenticated` role has: `access dungeoncrawler characters` ✓
- `content_editor` role has: (empty — inherits via authenticated user base role) ✓
- Fresh permissions re-validate: **0 violations** (confirmed against `/tmp/qa-revalidate/`)
- qa-permissions.json fix commit: `2af8c726b` (qa-dungeoncrawler preflight, 17:09 same day)

## Next actions
- QA: re-run site audit to generate a clean `20260322-HHMMSS` evidence artifact confirming 0 violations for release-next
- No dev code changes needed

## Blockers
- None

## ROI estimate
- ROI: 9
- Rationale: This was the only open QA FAIL blocking a clean release-next gate. Confirming it's a false positive with 0 code changes needed unblocks the release without introducing risk. Fast path to clean QA run.
