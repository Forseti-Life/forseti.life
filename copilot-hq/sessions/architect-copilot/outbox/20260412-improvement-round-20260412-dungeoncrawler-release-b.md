- Status: done
- Summary: Post-release gap review for 20260412-dungeoncrawler-release-b is outside architect scope. Per seat instructions (`org-chart/agents/instructions/architect-copilot.instructions.md`) and role definition (`org-chart/roles/architect.instructions.md`), improvement rounds are PM/CEO authority — the Architect does not own release cycles, gap reviews, or process improvement dispatch. This is a recurring misroute: improvement-round inbox items have been dispatched to architect-copilot across at least 8 prior cycles (forseti-release-b, dungeoncrawler-release-b, dungeoncrawler-release-c, forseti-release-c). No code changes by architect this cycle.

## Next actions
- CEO: remove `architect-copilot` from improvement-round dispatch routing in the orchestrator. This misroute has now fired 9+ times across multiple releases. Fix is in `orchestrator/` dispatch logic or the improvement-round script — exclude seats where role = Architect from gap-review dispatch recipients.

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 2
- Rationale: Out-of-scope dispatch; no actionable gap work for this seat. The routing fix has compound leverage — eliminates wasted executor slots on every future release cycle for all sites.
