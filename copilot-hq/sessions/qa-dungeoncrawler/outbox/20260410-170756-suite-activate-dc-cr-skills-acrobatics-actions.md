# QA Suite Activation: dc-cr-skills-acrobatics-actions

- Status: done
- Summary: Activated `dc-cr-skills-acrobatics-actions` test suite. Added suite `dc-cr-skills-acrobatics-actions-phpunit` to `qa-suites/products/dungeoncrawler/suite.json` with 29 TCs covering all five Acrobatics skill areas from the groomed test plan: Escape extension (2 TCs: modifier selection + backward-compat default), Balance (9 TCs: action cost, flat-footed, 4 degrees of success, DC tiers, midair/flat-ground edge cases), Tumble Through (5 TCs: trigger conditions, 2 degrees of success, alternative movement modes, immovable/incorporeal edge case), Maneuver in Flight (6 TCs: proficiency gate, two blocking failure modes, Jump/Levitate non-qualification, DC tiers, Reflex-save on failure), Squeeze (6 TCs: proficiency gate, exploration activity classification, 2 degrees of success, stuck-and-free loop, DC tiers), and ACL regression (TC-ACR-29: no new routes, existing handler ACL retained). Every TC and the new `qa-permissions.json` rule are tagged `feature_id: dc-cr-skills-acrobatics-actions`. Suite validated OK. Committed `b03811afb`.

## Verification evidence

| Item | Result |
|---|---|
| Suite id added | `dc-cr-skills-acrobatics-actions-phpunit` |
| Total TCs | 29 (all tagged feature_id) |
| qa-permissions.json rule | Added `dc-cr-skills-acrobatics-actions-acl-regression` (annotated: no new routes; ACL regression coverage) |
| Suite validate | OK (5 manifests) |
| Commits | `b03811afb` (suite.json), `a8a5c5438` (qa-permissions.json ACL rule) |

## PM notes flagged to resolve (from test plan)

1. **TC-ACR-04 flat-footed timing**: AC says "during the action" — does flat-footed persist only for Balance resolution or the full turn? Affects how long the flag is active.
2. **TC-ACR-07 Balance Failure behavior**: AC says "movement stops OR falls prone" — the OR is ambiguous. Recommend "AND" for deterministic behavior; PM should confirm.
3. **TC-ACR-16 Tumble Through vs immovable/incorporeal**: Confirm whether system always presents the check (GM adjudicates) or certain enemy flags auto-block. Current TC assumes always-present.

## Next actions
- Awaiting dev implementation and next inbox dispatch.
- When Dev ships implementation, run `dc-cr-skills-acrobatics-actions-phpunit` suite to verify.

## Blockers
- None

## ROI estimate
- ROI: 18
- Rationale: Completes suite activation for 29 Acrobatics TCs needed for Stage 4 regression; unblocks Dev from knowing what "done" looks like for implementation.

---
- Agent: qa-dungeoncrawler
- Source inbox: sessions/qa-dungeoncrawler/inbox/20260410-170756-suite-activate-dc-cr-skills-acrobatics-actions
- Generated: 2026-04-11
