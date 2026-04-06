- Status: done
- Summary: Applied CSRF FINDING-2 patches (2a/2b/2c) to all 3 routing files. In each case, `_csrf_token: TRUE` was under `options:` which Drupal's access checker ignores — moved it to `requirements:` and removed the ineffective `_method: 'POST'` requirement (method enforcement belongs in the `methods:` key). Verification via `csrf-route-scan.sh` scoped to `ai_conversation` and `agent_evaluation` shows zero flags. Gate 2 artifact written to `sessions/dev-infra/artifacts/csrf-finding-2-applied.txt`.

## Next actions
- Gate 2 artifact is ready for pm-infra review (`sessions/dev-infra/artifacts/csrf-finding-2-applied.txt`).
- Note: `csrf-route-scan.sh` overall exits 1 due to 17 pre-existing flags in `stlouisintegration` (job_hunter, job_application_automation) and Drupal core (user.routing.yml) — these are out of scope for FINDING-2.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 12
- Rationale: LLM endpoint CSRF — 4 escalation cycles blocked; patches were written; this was pure execution. Financial impact (API credit drain) adds urgency beyond a typical CSRF finding.

## Verification evidence
```
bash csrf-route-scan.sh /home/ubuntu/forseti.life | grep -E "ai_conversation|agent_evaluation"
→ ZERO flags for ai_conversation and agent_evaluation
```

## Commits
- `6b1fb830` — fix(security): CSRF FINDING-2 — move _csrf_token to requirements: in send_message routes
- `8e51577b` — hq: dev-infra artifact — csrf-finding-2-applied.txt (Gate 2 gate artifact)

## Files changed
- `sites/forseti/web/modules/custom/ai_conversation/ai_conversation.routing.yml` (FINDING-2a)
- `sites/dungeoncrawler/web/modules/custom/ai_conversation/ai_conversation.routing.yml` (FINDING-2b)
- `sites/forseti/web/modules/custom/agent_evaluation/agent_evaluation.routing.yml` (FINDING-2c)
- `sessions/dev-infra/artifacts/csrf-finding-2-applied.txt` (Gate 2 artifact — required by pm-infra)
