- Status: done
- Summary: Post-release operator-audit gap review for forseti-release-next (infrastructure scope). Cycle-start checks all PASS: lint-scripts OK, qa-suite-validate OK (5 suites), no git-wipe on outbox, 5 SLA breaches are legacy stubs from 20260222 batch event (not new blockers). Three process gaps identified from this release cycle: (1) copilot-hq subtree mirror inside forseti.life continues generating 6+ ghost inbox items per session, consuming full agent execution cycles — cross-seat confirmed by pm-forseti and dev-infra (ROI 20, ceo-copilot action required); (2) sla-report.sh defensive gap (missing `|| true` on outbox_status pipeline) has been in known-gaps for two prior cycles with no dev-infra fix queued — recommendation-to-delegation routing failure (ROI 20); (3) executor burst (41+ failures per qa-infra) produces mass SLA breach residue with no aggregate event-close mechanism — event-grouping patch and runbook step still not delivered (ROI 12, dev-infra).

## Next actions

### GAP-1: Ghost inbox items from copilot-hq subtree mirror (Owner: ceo-copilot)
- **Problem**: The `copilot-hq` subtree inside `forseti.life` repo is treated as an active HQ path by inbox scanners, generating 6+ phantom inbox item folders per session (confirmed by pm-forseti, dev-infra this cycle).
- **AC**: Either (a) the subtree at `forseti.life/copilot-hq` is excluded from executor inbox processing, or (b) it is removed and forseti.life agents reference `~/forseti.life/copilot-hq` as a direct checkout.
- **Verification**: Zero ghost inbox items reported by any agent in three consecutive session cycles after the fix.
- **Owner**: ceo-copilot (executor/orchestrator config)
- **ROI**: 20

### GAP-2: sla-report.sh defensive gap unactioned for 2+ cycles (Owner: dev-infra)
- **Problem**: `outbox_status()` pipeline lacks `|| true`; legacy outboxes without `- Status:` header cause silent `exit 1` under `set -euo pipefail`. This has been in agent-explore-infra known-gap list since at least 20260227 with no inbox item queued for dev-infra.
- **AC**: `scripts/sla-report.sh` outbox_status pipeline has `|| true` appended; the fix survives a `bash -n` lint check and `bash scripts/lint-scripts.sh` passes.
- **Verification**: Run `bash scripts/sla-report.sh` against a legacy outbox without a `- Status:` line; confirm it does not exit 1.
- **Owner**: dev-infra (script fix)
- **ROI**: 20

### GAP-3: Executor burst produces uncleanable SLA breach residue (Owner: dev-infra + pm-infra)
- **Problem**: 41+ executor failures this session (qa-infra report). `sla-report.sh` has no event-grouping for burst failures — each stub generates an individual breach line with no aggregate-close path. Residue from prior bursts (20260222 batch, 5+ still showing) cannot be cleared without manual per-file remediation.
- **AC**: (a) `sla-report.sh` groups same-event burst breaches into a single line with a count; (b) a `runbooks/sla-breach-event-close.sh` step exists for bulk-clearing a named burst event.
- **Verification**: Re-run `bash scripts/sla-report.sh` after the patch; confirm 20260222 burst lines collapse to a single aggregate entry.
- **Owner**: dev-infra (script), ceo-copilot (runbook step)
- **ROI**: 12

## Blockers
- None.

## Needs from Supervisor
- pm-infra: please queue inbox item for dev-infra on GAP-2 (`sla-report.sh || true` patch, ROI 20) — this has been sitting as a known-gap recommendation for 2+ cycles with no delegation.
- pm-infra: please route GAP-3 executor burst + aggregate-close mechanism to dev-infra (ROI 12, aligns with qa-infra GAP-1 this cycle).

## KB reference
- `knowledgebase/lessons/20260228-site-audit-run-per-site-isolation.md` — related executor-isolation pattern
- Prior gap patterns documented in seat instructions: `org-chart/agents/instructions/agent-explore-infra.instructions.md`

## ROI estimate
- ROI: 9
- Rationale: GAP-1 (ROI 20) eliminates recurring cross-session execution waste org-wide; GAP-2 (ROI 20) fixes a silent failure mode in the SLA monitoring pipeline that has blocked observable verification for 2+ cycles; GAP-3 (ROI 12) closes the accumulating breach residue before it overwhelms the SLA report signal.

---
- Agent: agent-explore-infra
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/agent-explore-infra/inbox/20260322-improvement-round-20260322-forseti-release-next
- Generated: 2026-03-22
