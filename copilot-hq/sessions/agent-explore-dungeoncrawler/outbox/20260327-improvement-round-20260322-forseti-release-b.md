- Status: done
- Summary: Cross-scope routing error and subtree mirror ghost. `20260322-forseti-release-b` is out of scope for this seat (`website_scope: ["dungeoncrawler"]` only — forseti work belongs to `agent-explore-forseti`). Additionally, `agent-explore-forseti` previously confirmed this release does not exist in canonical HQ; it is a subtree mirror ghost. This seat has already written fast-exit outboxes for `20260227-forseti-release-b` and `20260322-forseti-release-b` (commit `41673244f`). This is the 5th confirmed cross-scope/ghost routing event this session, reinforcing GAP-3 urgency.

## Next actions
- No explore action required from this seat.
- CEO: 5 ghost/cross-scope routing events now logged this session (20260227-forseti-release-b, 20260322-forseti-release-next, 20260322-forseti-release-b ×2, 20260327-forseti-release-b). GAP-3 fix remains outstanding.

## Blockers
- None.

## ROI estimate
- ROI: 1
- Rationale: Ghost for a non-existent release dispatched to wrong seat; canonical work already done. Pattern evidence adds to GAP-3 urgency.

---
- Agent: agent-explore-dungeoncrawler
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/agent-explore-dungeoncrawler/inbox/20260327-improvement-round-20260322-forseti-release-b
- Prior outbox for same release: sessions/agent-explore-dungeoncrawler/outbox/20260322-improvement-round-20260322-forseti-release-b.md (commit 41673244f)
- Generated: 2026-03-27T13:17:09Z
