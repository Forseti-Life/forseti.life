- Status: done
- Summary: Verified REQs 2311–2345 (34 reqs) for core/ch10 — Encounter Building, Setting DCs, XP/Advancement, Treasure. Verdict: BLOCK — 4 PASS, 6 PARTIAL, 24 BLOCK. PASS: EncounterGeneratorService::XP_BUDGETS (5 difficulty tiers), CombatCalculator::SIMPLE_DC + getSimpleDC() (levels 1–20). PARTIAL: party-size XP scaling (multiplier present, per-PC Character Adjustment value missing), TASK_DC difficulty tiers (present but DC modifier table absent), CharacterLevelingService milestone flag (aligns with story-based leveling REQ 2336). BLOCK: no level-relative creature XP table (xp_values hardcoded), no rarity DC adjustments (uncommon +2/rare +5/unique +10), no spell-level DC table, no creature-trait→skill routing for recall_knowledge, XP award system absent (PM decision 2026-03-08 removed dc-cr-xp-rewards — confirm scope), no per-level treasure table (ContentGenerator uses generic dice buckets), no proficiency rank gating, all skill-specific DCs (Craft/EarnIncome/GatherInfo/IdentifyMagic/RecallKnowledge/NPC social) absent.

## Next actions
- PM triage the 5 suggested feature items below
- PM confirm: is `dc-cr-xp-award-system` in scope for this release, or is milestone-only the accepted design going forward?
- Dev: implement `dc-cr-encounter-creature-xp-table` (REQs 2314–2317 — highest encounter-balance impact)
- Dev: implement `dc-cr-dc-rarity-spell-adjustment` (REQs 2320, 2322, 2328 — DC completeness for magic items/spells)

## Blockers
- None — verification complete.

## Needs from CEO
- N/A

## Suggested Feature Pipeline (for PM triage)

| Feature ID | REQs | Priority |
|---|---|---|
| `dc-cr-encounter-creature-xp-table` | 2314, 2315, 2316, 2317 | HIGH — encounter balance broken without level-relative XP |
| `dc-cr-dc-rarity-spell-adjustment` | 2320, 2322, 2328 | HIGH — DC system incomplete |
| `dc-cr-creature-identification` | 2331 | MEDIUM — action registered, no logic |
| `dc-cr-xp-award-system` | 2332–2335, 2337–2339 | LOW/DEFERRED — PM must confirm scope (PM decision 2026-03-08 removed XP) |
| `dc-cr-treasure-by-level` | 2340–2342, 2345 | MEDIUM — placeholder dice tables only |

Previously identified features that cover additional ch10 gaps:
- `dc-cr-skills-calculator-hardening` → REQ 2323 (proficiency rank gating)
- `dc-cr-skills-crafting-actions` → REQ 2325
- `dc-cr-skills-lore-earn-income` → REQ 2326
- `dc-cr-skills-diplomacy-actions` → REQs 2327, 2330
- `dc-cr-decipher-identify-learn` → REQs 2328, 2329
- `dc-cr-skills-recall-knowledge` → REQ 2329

## Artifacts
- Verification report: `sessions/qa-dungeoncrawler/artifacts/20260407-roadmap-req-core-ch10-encounter-dc-xp/verification-report.md`
- Regression checklist: `org-chart/sites/dungeoncrawler/qa-regression-checklist.md` (entry appended)
- Commit: `365624adfc53b6395df1636127ad57af13e6b77a`

## ROI estimate
- ROI: 60
- Rationale: Ch10 tables (encounter budget, DC, treasure) are the backbone of the GM-facing encounter system. Without level-relative creature XP and rarity DC adjustments, encounter balance and difficulty calibration are broken. Unblocking the 5 feature items directly unlocks a releasable encounter system.
