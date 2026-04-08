# Suite Activation: dc-apg-class-investigator

- Status: done
- Summary: Suite entry `dc-apg-class-investigator-e2e` was already activated in a prior session (commit `126907cfe`, Release-B). The entry contains 18 test cases (TC-INV-01 through TC-INV-18) covering class fundamentals (HP 8+Con, Int key ability, Expert Perception/Reflex/Will, Society auto-trained, skill count formula), Devise a Stratagem (fortune d20 store/apply/discard, Int mod substitution on qualifying weapons, free-action vs active lead), Strategic Strike precision damage scaling (1d6 to 5d6 at L1/5/9/13/17, no sneak-attack stack), Pursue a Lead (2-lead cap with auto-drop, specific entity target required), Clue In reaction (10-min cooldown), all 4 methodologies (Alchemical Sciences, Empiricism, Forensic Medicine, Interrogation), and edge cases — all tagged `"feature_id": "dc-apg-class-investigator"`. Security AC exemption confirmed (no new routes); no permission rules needed. `python3 scripts/qa-suite-validate.py` passes (OK: 5 manifests).

## Evidence
- Suite entry: `qa-suites/products/dungeoncrawler/suite.json` → id `dc-apg-class-investigator-e2e`, 18 test cases, `feature_id: dc-apg-class-investigator`, `required_for_release: true`
- Permissions: no new rules needed (security AC exemption: no new routes)
- Validation: `python3 scripts/qa-suite-validate.py` → OK: 5 manifests validated
- Prior activation commit: `126907cfe`
- Prior outbox: `sessions/qa-dungeoncrawler/outbox/20260408-044531-suite-activate-dc-apg-class-investigator.md`

## Next actions
- Suite live and ready for Stage 4 regression when Dev delivers implementation.
- Await next inbox dispatch.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 30
- Rationale: Status confirmation; work already complete. 18 TCs for Investigator class mechanics ready for Stage 4 regression without gate delay.

---
- Agent: qa-dungeoncrawler
- Inbox item: 20260408-194501-suite-activate-dc-apg-class-investigator
- Generated: 2026-04-08
