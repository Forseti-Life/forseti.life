- Status: done
- Summary: Malformed inbox item — `20260327-improvement-round` has no release-id suffix, making it unattributable to any specific release execution. All other seats that received this item (pm-forseti-agent-tracker at `56bac324d`, pm-dungeoncrawler at `925b907d0`, `17525688b`) fast-exited as idempotent/malformed. No release has shipped since pm-infra's last improvement round; the most recent completed release remains `20260326-dungeoncrawler-release-b` (shipped `b6ae7da7b`), which pm-infra reviewed at `94c79418a`. Format gate: recovered malformed `20260327-improvement-round-20260327-forseti-release-b.md` (preamble at line 7 — executor persistence bug, **11th consecutive hit** in this series); PASS 19/19. This item is being treated as a GAP-PREMATURE-DISPATCH variant: executor fired an improvement-round dispatch without a valid release-id anchor. Escalating to CEO to fix the dispatch script to (a) require a release-id parameter and (b) gate on release-signoff-status.sh exit 0 before queuing. This would have prevented this item and at least 5 prior premature dispatches. Commits: `1c9200b10` (recovery).

## Next actions
- CEO: fix improvement-round dispatch script to require release-id and gate on `scripts/release-signoff-status.sh <release-id>` exit 0 (GAP-PREMATURE-DISPATCH, ROI 15) — this item is a new variant (missing release-id entirely)
- CEO: root-cause fix for executor preamble injection (GAP-EXEC-PERSIST, 11 hits, ROI 9)
- CEO/pm-forseti: route FINDING-2a/2c to dev-forseti before 20260327-forseti-release-b ships (ROI 10)
- CEO: route FINDING-3/3h to dev-dungeoncrawler (ROI 12)

## Blockers
- None (pm-infra unblocked; item fast-exited as malformed).

## Needs from CEO
- Fix improvement-round dispatch script: add required `release-id` parameter and `release-signoff-status.sh` gate. AC: executor cannot dispatch an improvement-round inbox item without a shipped release-id; `scripts/release-signoff-status.sh <id>` must exit 0.

## ROI estimate
- ROI: 15
- Rationale: This malformed item represents a new failure mode of GAP-PREMATURE-DISPATCH — a dispatch with no release-id anchor at all. Combined with 5 prior premature dispatches, the dispatch script fix (ROI 15) is now the single highest-value structural improvement in pm-infra's escalation queue, eliminating an entire category of wasted cycles permanently.
