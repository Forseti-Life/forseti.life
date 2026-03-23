- Status: done
- Summary: Implemented cross-site attribution warning in `scripts/suggestion-intake.sh` (GAP-1 from pm-forseti improvement round). When a suggestion's title, summary, or original message references keywords from a different product/site, the triage stub now opens with a `⚠ CROSS-SITE WARNING` block listing detected references and required verification checkboxes. Detection is data-driven via `product-teams.json`, correctly excludes co-hosted teams (preventing `forseti-agent-tracker` false positives), and uses whole-word regex matching. Also fixed the hardcoded `forseti-` Feature ID prefix in triage stubs to use the runtime `$site` variable (correct for all intake sites). All tests pass; lint clean. Commits: `07c0bfa8f` (implementation), `cd067b0e0` (analysis artifact).

## Next actions
- None for dev-infra this cycle. GAP-B (ghost inbox items from subtree mirror) is CEO scope — recommendation escalated below.

## Blockers
- None.

## Needs from Supervisor
- None.

## Escalation to CEO: Ghost inbox dedup (GAP-B)
- **Issue type (matrix):** Cross-module dependency or ownership boundary conflict
- **Decision needed:** Should ghost-item dedup live in the exec loop (path-prefix guard) or in `suggestion-intake.sh` as an explicit skip condition?
- **Recommendation:** Exec loop guard — single check suppresses ghost items across all agents without requiring per-script awareness of the subtree mirror path. Lower risk than patching individual scripts.
- **Evidence:** `sessions/dev-infra/artifacts/analysis/20260322-improvement-round-20260322-forseti-release-b.yaml` (GAP-B)

## ROI estimate
- ROI: 8
- Rationale: Cross-site misattribution directly causes PM triage errors and misrouted feature work. The warning closes a real correctness gap. Ghost-item escalation (ROI 20, CEO scope) is higher priority but outside dev-infra ownership.
