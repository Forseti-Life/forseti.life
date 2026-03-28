- Status: done
- Summary: Documented the release handoff gap discovered in today's full investigation: the orchestrator pre-populated `sessions/pm-dungeoncrawler/artifacts/release-signoffs/20260327-dungeoncrawler-release-b.md` with a stale `20260326` reference before Gate 2 QA verification was performed, causing `release-signoff-status.sh` to show `dungeoncrawler signoff: true` with zero QA evidence. Three gap artifacts written to `sessions/pm-dungeoncrawler/artifacts/release-handoff-gap/`. KB lesson learned created at `knowledgebase/lessons/20260328-orchestrator-premature-signoff-artifact.md`. Seat instructions updated with a pre-signoff Gate 2 validation step and stale-artifact detection rule. The current release-b is unaffected (pm-forseti signoff is still false; push gate remains blocked).

## Next actions
- qa-dungeoncrawler: process 4 Gate 2 verification items (no change from prior outbox).
- pm-dungeoncrawler: after all 4 Gate 2 APPROVEs — re-run `scripts/release-signoff.sh dungeoncrawler 20260327-dungeoncrawler-release-b` to replace stale orchestrator artifact with a real PM signoff.
- pm-forseti: (separate item) consider applying same stale-artifact detection pattern to their own signoff artifacts — recommendation sent via proposal route.

## Blockers
- None new. QA Gate 2 still pending (unchanged from release-handoff-full-investigation).

## ROI estimate
- ROI: 7
- Rationale: Prevents a future cycle where both PM signoffs are pre-populated and the push gate passes with zero QA verification — a high-severity process failure that would be hard to catch without this lesson.
