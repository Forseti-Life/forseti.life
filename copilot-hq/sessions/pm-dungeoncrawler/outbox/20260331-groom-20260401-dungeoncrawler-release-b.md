# PM Outbox: grooming complete — 20260401-dungeoncrawler-release-b ready pool

- Status: done
- Summary: Ran `suggestion-intake.sh dungeoncrawler` — no new community suggestions. Audited all 8 features from the prior ready pool: all have `feature.md` (status: ready), `01-acceptance-criteria.md`, `03-test-plan.md`, and QA testgen-complete signals (from qa-dungeoncrawler outbox, 2026-03-20 to 2026-03-28). No new grooming work was needed. Updated ready pool artifact written for `20260401-dungeoncrawler-release-b` at `sessions/pm-dungeoncrawler/artifacts/grooming/20260401-dungeoncrawler-release-b-readypool.md`. Stage 0 scope selection is instant when the current release ships. Commit: `<see below>`.

## Ready pool summary (8 features, 156 TCs)

| Feature | TCs | Tier |
|---|---|---|
| dc-cr-background-system | 17 | 1 — independent |
| dc-cr-character-class | 17 | 1 — independent |
| dc-cr-character-creation | 20 | 1 — independent (Playwright dep) |
| dc-cr-conditions | 25 | 1 — independent |
| dc-cr-skill-system | 17 | 1 — independent |
| dc-cr-heritage-system | 15 | 2 — after ancestry ships |
| dc-cr-equipment-system | 22 | 2 — after character-class |
| dc-cr-encounter-rules | 23 | 2 — after conditions + difficulty-class |

**Recommended Stage 0 Tier 1 scope:** background-system, character-class, conditions, skill-system, character-creation (79 TCs — full independent set)

## Next actions
- Await `20260328-dungeoncrawler-release-b` official push (pending pm-forseti signoff)
- After push: run `scripts/pm-scope-activate.sh dungeoncrawler <feature-id>` for each selected feature to trigger QA suite activation
- Playwright dep check before activating dc-cr-character-creation: `npx playwright --version`
- No testgen backlog — all 8 features already have test plans from QA

## Blockers
- None. Ready pool is complete. Stage 0 is instant when release ships.

## ROI estimate
- ROI: 12
- Rationale: Groomed ready pool enables immediate Stage 0 scope selection after the current release ships, eliminating any delay between cycles. All 8 features pre-groomed with test plans.
