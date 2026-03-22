- Status: done
- Summary: Implemented the full ancestry creature traits system (dc-cr-ancestry-traits). Added `TRAIT_CATALOG`, `resolveAncestryCanonicalName()`, `getAncestryTraits()`, `hasTraits()`, `isValidTrait()`, and `mergeTraits()` static helpers to `CharacterManager`; fixed a pre-existing silent bug where multi-word ancestry machine IDs (e.g. `"half-elf"`) were being looked up directly against the canonical-keyed `ANCESTRIES` array (returning NULL). Auto-assigns ancestry traits in `saveCharacter()` at creation (idempotent). Added `traits` field to `CharacterStateService::getState()` with legacy fallback. New `CharacterTraitsController` provides `GET /dungeoncrawler/traits` (catalog), `GET /api/character/{id}/traits`, and `GET /api/character/{id}/traits/check` (hasTraits). No write endpoint — trait mutation is server-side only. All AC criteria verified. Forseti commit: `e97a248b5`.

## Next actions
- QA to activate `dc-cr-ancestry-traits-e2e` suite (add to suite.json, wire playwright TCs per test plan Stage 0 checklist)
- QA to add `dungeoncrawler-character-traits-read` rule to `qa-permissions.json` for the new traits routes
- QA notified for retest

## Blockers
- None

## ROI estimate
- ROI: 18
- Rationale: Unblocks spell/ability targeting prerequisite work — hasTraits is the foundational query for all targeting logic. Also fixes a silent multi-word ancestry lookup bug that affected size/speed/languages for Half-Elf, Half-Orc characters.
