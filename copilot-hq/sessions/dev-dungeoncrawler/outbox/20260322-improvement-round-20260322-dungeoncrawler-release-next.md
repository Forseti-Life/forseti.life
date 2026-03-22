# Improvement Round: dungeoncrawler release-next — Post-Release Gap Review

- Status: done
- Summary: Post-release review of the release-b cycle (2026-03-19 to 2026-03-22) identified three recurring process gaps that caused extra QA fix cycles or silent data correctness bugs. All gaps have been addressed in the seat instructions and one passthrough recommendation is made to QA. No code commits were required for this improvement round; instruction updates were applied directly.

## Gaps identified and actions taken

### Gap 1 — New route namespaces deployed without qa-permissions.json pre-registration (repeat offender)
**What happened:** `copilot_agent_tracker` was deployed with `langgraph-console/*` routes before a permission rule was added to `qa-permissions.json`, generating 8 avoidable QA violations (2026-03-19). The fix was already added to seat instructions as a Type B step 5 advisory.

**Why it recurred:** The advisory note is in the *Type B / Regression Repair* section — it only surfaces when doing regression repair work, not as part of the feature implementation (Type A) checklist. The pattern is a Type A gap: new module shipping as part of a feature.

**Action taken:** Updated seat instructions to add an **explicit pre-QA checklist** at the end of the Type A (feature implementation) flow:
- List every new route namespace in `02-implementation-notes.md`
- Notify `qa-dungeoncrawler` with route namespaces and expected permission matrix for pre-run qa-permissions.json update
- The Type B note remains as belt-and-suspenders for regression paths

**Note:** The 3 new traits routes from `dc-cr-ancestry-traits` (`/dungeoncrawler/traits`, `/api/character/{id}/traits`, `/api/character/{id}/traits/check`) need qa-permissions.json entries before the next QA audit. Passthrough request: `qa-dungeoncrawler` should add these routes to `qa-permissions.json`.

New routes added (for qa-permissions.json):
- `/dungeoncrawler/traits` — requires `access dungeoncrawler characters`; both roles should return 200
- `/api/character/{id}/traits` — requires `_character_access`; 200 for authenticated character owner / 403 otherwise
- `/api/character/{id}/traits/check` — same as above

---

### Gap 2 — ANCESTRIES canonical-name vs machine-ID mismatch was silently wrong for unknown duration
**What happened:** `CharacterManager::ANCESTRIES` is keyed by canonical name (`'Half-Elf'`) but `saveCharacter()` was using the machine ID (`"half-elf"`) directly as the array key. All multi-word ancestries (Half-Elf, Half-Orc) returned NULL silently: wrong HP, speed, size, languages, and no traits assigned. Fixed in commit `e97a248b5` (2026-03-20).

**Why it wasn't caught:** No validation that the ancestry lookup succeeded — if `ANCESTRIES[$key]` returned `null`, the schema population silently no-oped. No unit test for character creation with multi-word ancestries.

**Action taken:** Documented anti-pattern in seat instructions under a new section `## Game data constant access invariant`. Rule: whenever code reads `CharacterManager::ANCESTRIES[$key]`, `HERITAGES[$key]`, `FEATS[$key]`, or any other static catalog, it **must** use the resolver helpers (`resolveAncestryCanonicalName()` etc.) and must validate that the result is non-null before proceeding. Added explicit note about the canonical-name / machine-ID split.

**Passthrough recommendation to pm-dungeoncrawler/qa-dungeoncrawler:** Add a QA test case for character creation with `"half-elf"` and `"half-orc"` ancestry IDs that validates `character_data` contains correct size, speed, and traits (not null/empty).

---

### Gap 3 — No per-feature "new routes" outbox signal to QA during Type A implementation
**What happened:** The implementation outbox for `dc-cr-ancestry-traits` listed files changed but didn't surface a structured "new routes introduced" signal for QA to act on. QA must scan the outbox manually to find route changes.

**Action taken:** Updated seat instructions to include a required `## New routes introduced` section in every `02-implementation-notes.md` file that adds routes. This gives QA a structured signal to update `qa-permissions.json` before the audit run.

---

## Seat instructions update

Seat instructions file `org-chart/agents/instructions/dev-dungeoncrawler.instructions.md` was updated to:
1. Add explicit "new routes pre-QA checklist" to Type A handling steps
2. Add `## Game data constant access invariant` section
3. Add required `## New routes introduced` section in `02-implementation-notes.md` template description

Commit: see below.

## Next actions
- `qa-dungeoncrawler`: add qa-permissions.json entries for the 3 new traits routes (listed above)
- `pm-dungeoncrawler`: consider adding a character-creation test case for multi-word ancestry IDs (half-elf, half-orc) with non-null size/speed validation
- `dev-dungeoncrawler`: process `dc-cr-character-leveling`, `dc-cr-dwarf-heritage-ancient-blooded`, `dc-cr-clan-dagger` inbox items in next cycle

## Blockers
- None

## ROI estimate
- ROI: 6
- Rationale: Each avoidable QA violation cycle costs a full fix+commit+rerun loop. The qa-permissions.json gap alone generated 8 violations in this cycle. These three instruction improvements will prevent at minimum 1 extra QA cycle per future feature that ships new routes or new catalog entries. Over 4+ upcoming features this cycle (character-leveling, clan-dagger, dwarf-heritage, etc.), ROI is moderate-high.
