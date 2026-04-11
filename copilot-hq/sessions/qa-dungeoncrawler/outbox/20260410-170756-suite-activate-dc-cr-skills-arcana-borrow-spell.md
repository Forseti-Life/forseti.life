# QA Suite Activation: dc-cr-skills-arcana-borrow-spell

- Status: done
- Summary: Activated `dc-cr-skills-arcana-borrow-spell` test suite. Added suite `dc-cr-skills-arcana-borrow-spell-phpunit` to `qa-suites/products/dungeoncrawler/suite.json` with 11 TCs (9 immediately activatable, 2 deferred pending `dc-cr-spellcasting`). Activatable TCs cover: Arcana knowledge domain + creature identification + planar lore (TC-ARC-01–03), Recall Knowledge untrained gate (TC-ARC-04), Borrow as exploration activity (TC-ARC-05), dual-gate blocking logic (TC-ARC-06–08 — Untrained gate, non-arcane-prepared gate, both-gate-required edge case), and ACL regression (TC-ARC-11). Deferred TCs (TC-ARC-09–10: Borrow success/failure slot interaction) wait on `dc-cr-spellcasting`. ACL regression rule added to `qa-permissions.json`. Suite validated OK. Committed `b0b53422a`.

## Verification evidence

| Item | Result |
|---|---|
| Suite id added | `dc-cr-skills-arcana-borrow-spell-phpunit` |
| Total TCs | 11 (9 immediately activatable, 2 deferred) |
| required_for_release TCs | 9 (deferred TCs set false) |
| qa-permissions.json rule | `dc-cr-skills-arcana-borrow-spell-acl-regression` |
| Suite validate | OK (5 manifests) |
| Commit | `b0b53422a` |

## PM notes flagged

1. **TC-ARC-02 arcane creature type list:** AC says "arcane creature identification" but does not enumerate creature types. Constructs/dragons/elementals are examples only. Need canonical list to lock down the assertion set.
2. **TC-ARC-09/10 sequencing:** Borrow outcome TCs are deferred on `dc-cr-spellcasting`. Recommend Borrow an Arcane Spell NOT enter full release scope until dc-cr-spellcasting ships.
3. **TC-ARC-10 borrow retry scope:** AC says "next preparation cycle" — confirm whether this resets per daily prep (per-character-per-day) or per session. Current TC assumes daily-prep reset.

## Deferred TCs summary

| TC | Dependency | Reason |
|---|---|---|
| TC-ARC-09 | `dc-cr-spellcasting` | Borrow success: slot availability + preparation list integration |
| TC-ARC-10 | `dc-cr-spellcasting` | Borrow failure: retry-blocked state + prep-cycle reset |

## Next actions
- Awaiting dev implementation; run `dc-cr-skills-arcana-borrow-spell-phpunit` when implementation ships.
- TC-ARC-09 and TC-ARC-10 to be activated when `dc-cr-spellcasting` is implemented.

## Blockers
- None

## ROI estimate
- ROI: 16
- Rationale: 9 activatable TCs unblock Dev on Arcana gating logic; 2 deferred TCs already scoped with explicit dependency, preventing a future blocker investigation cycle.

---
- Agent: qa-dungeoncrawler
- Source inbox: sessions/qa-dungeoncrawler/inbox/20260410-170756-suite-activate-dc-cr-skills-arcana-borrow-spell
- Generated: 2026-04-11
