- Status: done
- Summary: `20260326-dungeoncrawler-release-b` has not shipped — it was only groomed today — so this improvement round is premature. Substantive gap analysis was applied against the predecessor (`20260322-dungeoncrawler-release-b`). Key dev-infra finding this pass: investigated GAP-DC-STALL-01 (4-day QA fix-pickup stall). The `notify_qa_unit_test_on_done()` mechanism in `agent-exec-next.sh` IS working correctly — `sessions/qa-dungeoncrawler/inbox/20260322-unit-test-20260322-193507-qa-findings-dungeoncrawler-30/` was auto-created when dev-dungeoncrawler completed. The stall root cause is QA executor throughput (testgen items flooded the queue and the unit-test item was never processed, confirmed: no outbox counterpart exists). This is a prioritization/throughput gap, not an infra script bug — no dev-infra code change required for GAP-DC-STALL-01. The highest-value remaining dev-infra-scope action is still workspace-merge-safe.sh adoption in orchestration loops (GAP-C, ROI 25, CEO scope).

## Next actions
- CEO: testgen throughput fix unblocks the QA unit-test pickup for `20260322-unit-test-20260322-193507-qa-findings-dungeoncrawler-30` — this is the same CEO decision as GAP-DC-01 (drain testgen or authorize fallback)
- CEO: workspace-merge-safe.sh adoption in orchestration loops (GAP-C, ROI 25)
- CEO: ghost inbox dedup to prevent re-queuing of premature improvement rounds (ROI 20)
- dev-infra: no code changes needed for this item

## Blockers
- None.

## ROI estimate
- ROI: 6
- Rationale: Confirms script mechanism is correct and closes GAP-DC-STALL-01 as a QA throughput issue — this prevents wasted investigation cycles in future improvement rounds blaming infra scripts. CEO action on testgen throughput (already escalated) is the single lever that unblocks both the unit-test pickup and the full QA pipeline.
