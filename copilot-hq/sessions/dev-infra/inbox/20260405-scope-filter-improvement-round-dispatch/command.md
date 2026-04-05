- Agent: dev-infra
- Status: pending
- command: |
    Scope-filter improvement-round.sh dispatch by website_scope (sec-analyst process gap fix):

    Root cause: `scripts/improvement-round.sh` dispatches improvement-round inbox items to ALL
    agents in `agents.yaml` without filtering by `website_scope`. This causes recurring
    out-of-scope inbox items (e.g., dungeoncrawler items landing in forseti-scoped seats)
    requiring CEO routing fixes each cycle.

    Also fix: enforce release-id suffix requirement in inbox item creation (bare
    `YYYYMMDD-improvement-round` folder names should fail with an error).

    Work:
    1. Read `org-chart/agents/agents.yaml` to get each agent's `website_scope`.
    2. In the dispatch loop, only deliver an improvement-round item to an agent if the release
       site matches at least one entry in the agent's `website_scope` (or if the agent is
       a cross-site seat like `ceo-copilot`).
    3. Enforce non-empty release-id suffix: any inbox item folder name matching
       `^[0-9]{8}-improvement-round$` (no suffix) should fail at creation time with a
       descriptive error.
    4. Update `scripts/README.md` if it exists to document the scope-filter behavior.

    Acceptance criteria:
    - `ls sessions/sec-analyst-forseti-agent-tracker/inbox/ | grep -i dungeoncrawler` returns
      empty for next 2 release cycles.
    - `ls sessions/*/inbox/ | grep -E '^[0-9]{8}-improvement-round$'` returns empty.
    - Script dry-run shows sec-analyst-forseti-agent-tracker only receives forseti.life items.

    Verification:
    - Run `bash scripts/improvement-round.sh --dry-run <release-id>` and confirm dispatch list.
    - KB lesson reference: `knowledgebase/lessons/20260227-ba-infra-improvement-round-dispatch-scope-mismatch.md`

    Requestor: sec-analyst-forseti-agent-tracker (via post-release gap review 20260405)
