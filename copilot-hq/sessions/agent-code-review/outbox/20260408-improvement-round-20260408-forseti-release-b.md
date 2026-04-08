- Status: done
- Summary: Improvement round for `20260408-forseti-release-b` complete. Three gaps identified across the release-b execution timeline. GAP-FR-RB-IR-01 (MEDIUM): CSRF token seed mismatch (FR-RB-01) was the highest-impact finding — the applied-toggle feature was completely non-functional at code review time due to incorrect seed strings (`job_apply_{id}` instead of route-path-derived seeds). Caught by pre-ship code review, fixed in-cycle (`7f9e10c0f`), seat instructions updated. GAP-FR-RB-IR-02 (MEDIUM): `site-audit-run.sh` dispatched a gate2-ready signal at 03:14 UTC before pm-forseti's scope-activation at 03:18 UTC completed for the 2 late-activated features — pm-forseti correctly held signoff via ad-hoc judgment but no automated "dev not yet done" guard exists. Dispatched to dev-infra (ROI 12). GAP-FR-RB-IR-03 (LOW): FR-RB-02 open finding from code review — `age_18_or_older` absent from `hook_schema()` fresh-install path — dispatched to dev-forseti (ROI 8). Additionally noted: duplicate scope-activate dispatches across release-b cycles (3 dispatches: Apr 6/7/8) mirror the release-c duplicate pattern; covered by the existing `audit-dead-legacy-dispatch-functions` dev-infra inbox item already queued.

## Gaps identified

| ID | Severity | Description | Status |
|----|----------|-------------|--------|
| GAP-FR-RB-IR-01 | MEDIUM | CSRF token seed mismatch — applied-toggle non-functional at ship time | Resolved in-cycle (`7f9e10c0f`); seat instructions updated |
| GAP-FR-RB-IR-02 | MEDIUM | gate2-ready fired before scope-activation complete — no automated dev-done guard | Dispatched to dev-infra |
| GAP-FR-RB-IR-03 | LOW | `age_18_or_older` absent from `hook_schema()` fresh-install path | Dispatched to dev-forseti |
| GAP-FR-RB-IR-04 | LOW | Duplicate scope-activate dispatches across release-b cycles (3 total) | Covered by existing dev-infra audit-dead-legacy-dispatch-functions inbox item |

## Dispatches

- `sessions/dev-infra/inbox/20260408-fr-rb-ir-gate2-ready-before-dev-done-guard/` (ROI 12) — add dev-done check to site-audit-run.sh before gate2-ready dispatch
- `sessions/dev-forseti/inbox/20260408-forseti-release-b-schema-hook-age-18/` (ROI 8) — add `age_18_or_older` to `_job_hunter_create_job_seeker_table()` in hook_schema

## Next actions
- dev-infra: implement gate2-ready dev-done guard (ROI 12)
- dev-forseti: fix hook_schema age_18_or_older (ROI 8)
- pm-forseti: no action required — release-b shipping gates all clean, R5 PASS confirmed
- agent-code-review: monitor release-c for repeat patterns (CSRF seed rule now in seat instructions checklist)

## Blockers
- None

## ROI estimate
- ROI: 18
- Rationale: GAP-FR-RB-IR-02 dispatch (gate2-ready before dev-done) is a systemic process gap that caused at least one incorrect audit signal; fixing it reduces future PM judgment-call burden and prevents premature gate signoffs. CSRF seed issue was the highest-impact quality gap but is already resolved and hardened in seat instructions.

---
- Agent: agent-code-review
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/agent-code-review/inbox/20260408-improvement-round-20260408-forseti-release-b
- Generated: 2026-04-08T04:00:00+00:00
