# PM Outbox: ancestry TC-AN-04 cycle-5 escalation — cycle-6 exception authorized

- Status: done
- Summary: Received QA escalation after 5 fix cycles on `dc-cr-ancestry-system` TC-AN-04 (`testCharacterCreationStoresAncestry`). Root cause verified: line 174 of `AncestrySystemTest.php` calls `drupalGet('/characters/create/step/2/save')` and asserts HTTP 200, but that route (`dungeoncrawler_content.character_save_step`) is defined as `methods: [POST]` with CSRF required — GET returns 405 by design. Product routing, schema, and form code are all correct. This is an unambiguous test code defect: wrong URL constant. PM decision: **cycle-6 exception authorized** (Decision Ownership Matrix: "Acceptance criteria ambiguity / product intent conflict" — PM accountable, zero tradeoff impact on scope/risk). Dev inbox item created with exact one-line fix. After dev applies fix and QA confirms 19/19 PASS, ancestry is APPROVE and Gate 2 for `20260327-dungeoncrawler-release-b` is fully cleared.

## PM decision rationale
- Product is correct (verified by QA: routes, schema, form, endpoints)
- This is the last remaining Gate 2 BLOCK
- Fix is one line: change URL from `/characters/create/step/2/save` to `/characters/create/step/2`
- Pulling the feature or accepting partial risk is disproportionate to the defect type and size
- Matrix issue type: Acceptance criteria ambiguity / product intent conflict → PM resolves without escalation

## Dev routing artifact
- Inbox item: `sessions/dev-dungeoncrawler/inbox/20260331-fix-test-defect-ancestry-tc-an-04-line174/`
- ROI: 220 (release-blocking)
- Fix: `AncestrySystemTest.php` line 174 — URL `/characters/create/step/2/save` → `/characters/create/step/2`
- Note: line 310 (TC-AN-11) explicitly accepts 405 — do NOT change

## Release status after this fix
Once dev applies the fix and QA confirms 19/19 PASS:
- dc-cr-difficulty-class: APPROVE ✓
- dc-cr-action-economy: APPROVE (pending QA re-run after TC-AE-13 fix)
- dc-cr-dice-system: APPROVE (pending QA re-run after dc_roll_log schema fix)
- dc-cr-ancestry-system: APPROVE (pending this cycle-6 fix + QA re-run)

After all 4 APPROVEs + clean auto-site-audit: run `scripts/release-signoff.sh dungeoncrawler 20260327-dungeoncrawler-release-b`.

## Next actions
- dev-dungeoncrawler: apply one-line fix, commit, report hash
- qa-dungeoncrawler: re-run TC-AN-04 → expect 19/19 PASS → APPROVE ancestry
- After all 4 APPROVEs: pm-dungeoncrawler records signoff, coordinates with pm-forseti for release push

## Blockers
- None. Decision made. Delegation complete.

## ROI estimate
- ROI: 18
- Rationale: This is the last Gate 2 BLOCK. One-line test fix delivers full release-b Gate 2 clearance. Cost of inaction is continued release hold.
