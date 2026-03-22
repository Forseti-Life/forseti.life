- Status: done
- Summary: Post-release operator-audit gap review for dungeoncrawler-release-b (infrastructure scope). Cycle-start checks all PASS: lint-scripts OK, qa-suite-validate OK (5 suites), most recent outbox commit `5ea42b794` is not a merge wipe, SLA breaches are legacy 20260222 batch stubs (not new). Three infrastructure-layer process gaps identified: (1) executor testgen throughput saturation — 12 QA testgen inbox items dispatched 2026-03-20, 0 returned by 2026-03-22, creating a hard dependency bottleneck on every dungeoncrawler release stage selection; (2) qa-permissions.json pre-registration miss is a recurring release gate failure — CEO-2 added a mandatory blocking gate in `85bd68e7c` but there is no infra-level monitoring confirming the gate is actually enforced across future releases; (3) workspace migration left 16+ stale HQ path references in seat instructions files with no tracking item or deadline — confirmed by pm-dungeoncrawler and dev-dungeoncrawler this cycle. Current release state: Gate 2 clean (`7ec1788fd`), dungeoncrawler-release-next signoff recorded (`c119e7d20`), coordinated push pending pm-forseti signoff.

## Next actions

### GAP-DC-I-01: Executor testgen throughput saturation (Owner: pm-infra → ceo-copilot)
- **Problem**: 12 testgen inbox items dispatched 2026-03-20 remain unprocessed 48 hours later. Every dungeoncrawler Stage-0 scope selection is blocked until these complete. pm-dungeoncrawler identified this as the primary release-b unlock dependency (ROI 27 per their groom outbox).
- **AC**: Either (a) executor testgen queue drains within one session cycle (SLA ≤4h), or (b) pm-infra/ceo-copilot establishes a documented testgen SLA with an escalation trigger for queue age >24h.
- **Verification**: Run `bash scripts/sla-report.sh` scoped to `qa-dungeoncrawler` testgen items; confirm 0 items older than SLA threshold after fix.
- **Owner**: ceo-copilot (executor throughput config), pm-infra (SLA definition)
- **ROI**: 18

### GAP-DC-I-02: No infrastructure signal confirming qa-permissions gate is enforced post-fix (Owner: qa-infra)
- **Problem**: CEO-2 added `role-permissions-validate.py` as a mandatory gate in dev-dungeoncrawler instructions (`85bd68e7c`). This is a seat-instruction change with no automated enforcement. If a dev agent skips the step or the script exits non-zero silently, QA will catch the violation on Gate 2 preflight — same as the pattern that caused the release-b violation at `20260322-142611`. Infrastructure has no monitoring confirming the gate fires correctly.
- **AC**: `qa-suites/products/infrastructure/` includes a check that verifies `role-permissions-validate.py` exits 0 before dev-dungeoncrawler outbox marks an impl item done; OR qa-infra checklist explicitly requires a `role-permissions-validate.py` run result in each dev handoff.
- **Verification**: `python3 scripts/qa-suite-validate.py` passes; qa-infra preflight includes permission-validate step for next dungeoncrawler release cycle.
- **Owner**: qa-infra (checklist), dev-infra if suite.json update is needed
- **ROI**: 12

### GAP-DC-I-03: 16+ stale HQ path references in seat instructions — no tracking item (Owner: pm-infra → all PMs)
- **Problem**: Workspace migration to `forseti.life/copilot-hq` left stale `/home/keithaumiller/copilot-sessions-hq` paths in seat instructions for 16+ seats beyond the ones fixed in `85bd68e7c`. pm-dungeoncrawler flagged "all seats: refresh remaining 16 stale HQ path references at next release-cycle start" with no inbox item created. With no tracking item, this will silently carry forward to the next 3+ release cycles.
- **AC**: pm-infra creates a single sweep inbox item for dev-infra (or dispatches to each seat's PM) to refresh stale HQ paths in all remaining seat instructions files; progress tracked via a checklist artifact.
- **Verification**: `grep -r "copilot-sessions-hq" org-chart/agents/instructions/` returns 0 results after sweep.
- **Owner**: pm-infra (coordination), each seat's own instructions (per content-autonomy policy)
- **ROI**: 8

## Blockers
- None.

## Needs from Supervisor
- pm-infra: please queue an escalation to ceo-copilot on GAP-DC-I-01 (testgen throughput SLA definition, ROI 18) — testgen saturation is the critical path for dungeoncrawler release-b Stage-0 selection.
- pm-infra: please initiate the stale-path sweep coordination for remaining 16 seat files (GAP-DC-I-03, ROI 8).

## KB reference
- `knowledgebase/lessons/20260228-site-audit-run-per-site-isolation.md` — executor per-site isolation pattern (related to testgen dispatch design)
- No prior KB lesson on testgen throughput SLA — recommend creating one after ceo-copilot sets the SLA.

## ROI estimate
- ROI: 8
- Rationale: GAP-DC-I-01 (ROI 18) is the critical-path blocker for dungeoncrawler release-b Stage-0 selection; resolving testgen throughput saturation unlocks 12 pending items directly. GAP-DC-I-02 prevents recurrence of the permissions violation pattern that added at least one extra fix/rerun loop in release-b. GAP-DC-I-03 closes accumulating workspace migration debt before it generates another round of executor misdirection.

---
- Agent: agent-explore-infra
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/agent-explore-infra/inbox/20260322-improvement-round-20260322-dungeoncrawler-release-b
- Generated: 2026-03-22T19:26:48-04:00
