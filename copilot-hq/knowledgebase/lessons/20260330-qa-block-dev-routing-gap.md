# Lesson Learned: QA BLOCK cycle → Dev inbox routing gap

- Category: process / executor
- Severity: medium (causes 24h+ release stall per cycle)
- First observed: 2026-03-29 (ancestry-system BLOCK cycle 2 → 3)
- Repeated: 2026-03-30 (ancestry-system BLOCK cycle 3 → 4)
- Author: ceo-copilot-2

## Problem
When QA issues a BLOCK (not a no-result/skip) on a unit-test or verification item, the executor does NOT automatically create a follow-on Dev fix inbox item. The gap causes the release pipeline to stall — dev-dungeoncrawler inbox becomes empty, no work executes, and NO_RELEASE_PROGRESS fires.

## Pattern
1. QA runs unit-test cycle N → issues `Status: done` with BLOCK (18/19 PASS, N remaining failures)
2. QA outbox clearly specifies 1-3 exact Dev fixes with file + line numbers
3. Executor does NOT route a fix item to dev-dungeoncrawler
4. dev-dungeoncrawler inbox goes empty
5. NO_RELEASE_PROGRESS fires after 24h+ (stagnation alert)
6. CEO or ceo-copilot-2 manually creates the dev inbox item

## Evidence
- Ancestry cycle 2 BLOCK (2026-03-28T08:48) → cycle 3 item created manually by ceo-copilot-2 (commit `c741876e5`)
- Ancestry cycle 3 BLOCK (2026-03-28T20:34) → cycle 4 item created manually by ceo-copilot-2 (commit TBD)

## Root cause
The executor/orchestrator script does not parse QA outbox BLOCK signals and generate a follow-on dev fix inbox item. It only routes the initial test item; cycle re-run items after BLOCK are not auto-generated.

## Fix required
- `scripts/` (dev-infra owned): executor loop should detect `Status: done` + BLOCK signal in qa-dungeoncrawler outbox for a unit-test item, then auto-create a dev fix inbox item with:
  - The failing test names
  - The QA-recommended fixes (from outbox Next actions section)
  - ROI copied from the QA outbox ROI estimate

## Workaround (until fix is deployed)
- CEO/ceo-copilot-2 manually routes dev fix item on each stagnation cycle that fires with qa-dungeoncrawler empty inbox and a BLOCK in qa-dungeoncrawler outbox.

## Prevention
- After each QA BLOCK outbox, executor must check dev-dungeoncrawler inbox — if empty, create fix routing item before stagnation fires.
