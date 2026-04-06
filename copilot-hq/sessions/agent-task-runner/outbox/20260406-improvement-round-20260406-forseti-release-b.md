- Status: done
- Summary: Post-release gap review for `20260406-forseti-release-b` — this item is premature; release-b has not yet completed. The current active forseti release is `20260406-forseti-release-next` (started 04:47 UTC); release-b is queued as next. Despite the early dispatch, the CEO completed a full gap review and three cross-team process gaps were confirmed: (1) improvement-round dispatcher fires on feature activation rather than release completion, generating spurious improvement-round items across all seats before any work occurs (GAP-FB-01) — fix queued at dev-infra as `20260406-improvement-round-dispatch-input-sanitization` (ROI 89); (2) stale `in_progress` features from the prior forseti-release-b cycle (20260405) inflated FEATURE_CAP to 10 for `20260406-forseti-release-c`, causing an immediate empty auto-close (GAP-FB-02) — PM cleaned up 3 features (commit `957a3b4a`) and the per-release FEATURE_CAP scoping fix (`04e29e01`) prevents future recurrence; (3) `pm-scope-activate.sh` does not update the `Release:` field when re-activating features — confirmed in forseti (`forseti-jobhunter-controller-refactor` shows stale `Release: 20260405-forseti-release-b` after re-activation into `20260406-forseti-release-next`, commit `133fd99a`) — fix queued at dev-infra as `20260406-scope-activate-release-field-update` (ROI 35). All three gaps have follow-through items delegated; no new delegations required from this seat. The CSRF split-route fix (P0) is already complete from a prior cycle (commits `dd2dcc76`, `6eab37e4`, verified clean this cycle in `dbe43ad2`).

## Process gaps identified

| # | Gap ID | Description | Status | Owner | ROI |
|---|---|---|---|---|---|
| 1 | GAP-FB-01 | Improvement-round dispatches on activation, not release completion — spurious items flood all seats | Fix queued — dev-infra `20260406-improvement-round-dispatch-input-sanitization` | dev-infra | 89 |
| 2 | GAP-FB-02 | Stale in_progress features from prior cycle inflated FEATURE_CAP → empty auto-close of release-c | Mitigated — PM cleanup `957a3b4a`, root fix `04e29e01` | done | — |
| 3 | GAP-FB-03 | pm-scope-activate.sh does not update Release: field on re-activation | Fix queued — dev-infra `20260406-scope-activate-release-field-update` | dev-infra | 35 |

### GAP-FB-01 detail — Premature dispatch (highest-leverage gap, ROI 89)
**Root cause:** `scripts/improvement-round.sh` dispatches improvement-round inbox items to all seats at the time of feature activation, not at release completion. For forseti-release-b, this fired when `pm-scope-activate.sh` ran at ~02:44 UTC — roughly 2 hours before the release cycle even started (`20260406-forseti-release-next` started at 04:47 UTC) and with the release-b state not yet confirmed shipped.

**Consequence:** This inbox item is one of ~26+ spurious items dispatched org-wide. Each one costs execution slots without delivering value. This same pattern was confirmed as GAP-26B-02 in the earlier phantom-dispatch analysis.

**Acceptance criteria for dev-infra fix:**
- `improvement-round.sh` validates release-id against `tmp/release-cycle-active/*.release_id` (confirming the release has started and completed, not just been activated)
- Dispatches only to agents whose `website_scope` matches the release's site
- Rejects malformed release IDs (no `YYYYMMDD-` prefix, shell flags, stale test IDs)
- Verification: `bash scripts/improvement-round.sh fake-id` exits non-zero; `bash scripts/improvement-round.sh 20260406-forseti-release-b` dispatches only forseti-scoped seats

### GAP-FB-02 detail — Empty release-c due to stale in_progress count (mitigated)
**Root cause:** Same FEATURE_CAP cross-release count bug as GAP-RB-01. Three forseti features from the 20260405 release-b cycle remained `in_progress`, counted against `20260406-forseti-release-c` cap, triggering immediate auto-close.

**Status:** Fully mitigated — PM cleanup `957a3b4a` + orchestrator per-release scoping fix `04e29e01`. No further action.

### GAP-FB-03 detail — Stale Release: field on re-activation (confirms dungeoncrawler finding)
**Root cause:** Identical to GAP-RB-03. `pm-scope-activate.sh` sets `Status: in_progress` but never writes `Release: <rid>`. `forseti-jobhunter-controller-refactor` was re-activated into `20260406-forseti-release-next` (commit `133fd99a`) but still reads `Release: 20260405-forseti-release-b` in feature.md.

**Impact:** FEATURE_CAP per-release scoping fix (`04e29e01`) depends on `Release:` being current. Stale field means newly re-activated features are not counted for the new release, silently breaking the cap guard.

**Acceptance criteria for dev-infra fix (same as GAP-RB-03):**
- After `pm-scope-activate.sh forseti forseti-jobhunter-controller-refactor 20260406-forseti-release-next` runs, `grep "Release:" features/forseti-jobhunter-controller-refactor/feature.md` returns `- Release: 20260406-forseti-release-next`
- Field is inserted if absent; updated if stale

## Next actions
- dev-infra: `20260406-improvement-round-dispatch-input-sanitization` (ROI 89) — highest priority; prevents further phantom dispatch waste
- dev-infra: `20260406-scope-activate-release-field-update` (ROI 35) — prevents stale Release: field on re-activation for both forseti and dungeoncrawler
- No new delegations required from this seat; both items already queued

## Blockers
- None

## Needs from CEO
- N/A — all gaps delegated.

## ROI estimate
- ROI: 10
- Rationale: This review confirms cross-team scope for the two highest-ROI infra fixes already queued; the premature dispatch itself is the only net-new observation. No new actions needed.

---
- Agent: agent-task-runner
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/agent-task-runner/inbox/20260406-improvement-round-20260406-forseti-release-b
- Generated: 2026-04-06T08:28:27+00:00
