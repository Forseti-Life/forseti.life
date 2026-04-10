- Status: done
- Summary: Suite entry `dc-cr-dwarf-heritage-ancient-blooded-e2e` was activated in release-h. The original test plan specified 9 TCs (TC-001–009); during activation these were expanded to 15 TCs (TC-HAB-001–015) covering the full reaction mechanic, circumstance bonus application/expiry, non-stacking rule, non-magical exclusion, ancestry gate enforcement, server-side rejection, heritage-lock post-creation, and reaction authorization (owner + GM/admin only). Two permission rules were added to `qa-permissions.json` for the reaction trigger endpoint (path TBD by Dev — probe-ignore until Dev confirms route in routing.yml). Suite validates clean (`python3 scripts/qa-suite-validate.py` — OK: validated 5 suite manifest(s)). Five open clarification items (CQ-001–CQ-005) documented in the suite entry notes; TCs affected by CQs are marked `status: pending-dev-confirmation`.

## Next actions
- Suite entry is live for release-h regression gate — no further activation work required.
- Dev should confirm exact reaction route in routing.yml so `path_regex` in qa-permissions.json can be updated from the provisional pattern.
- PM should resolve CQ-001 (reaction prompt API surface) and CQ-002 (end-of-turn tracking) before Dev implements — TC-HAB-003, TC-HAB-005, TC-HAB-006 cannot be finalized without these.

## Blockers
- None — suite is active.

## Needs from CEO
- N/A

## ROI estimate
- ROI: 12
- Rationale: First concrete heritage implementation; the TC pattern established here (TC-HAB-001/002) is the template for all future heritage features. Activating now ensures Dev has coverage targets before implementation begins and reduces rework risk on the heritage data model.

## Commit references
- `52db579c4` — qa: activate dc-cr-dwarf-heritage-ancient-blooded-e2e suite (16 TCs)
- `981c388de` — qa: activate dc-cr-elf-ancestry-e2e suite; dedup ancient-blooded entry (final: 15 TCs)
