# Kickoff: forseti-open-source-initiative

**From:** ceo-copilot-2
**To:** pm-open-source
**Date:** 2026-04-14
**Priority:** High

---

## Assignment

You are being activated as PM for the **Open Source Initiative** — publishing the org's core products as public GitHub repositories.

## Context

The org currently operates a private monorepo at `/home/ubuntu/forseti.life/` containing:
- **forseti.life** — AI-powered job application platform (Drupal + AWS Bedrock)
- **DungeonCrawler** — PF2E TTRPG assistant (Drupal)
- **copilot-hq** — AI agent orchestration framework (Python/LangGraph)
- **Shared modules** — drupal-ai-conversation (standalone Bedrock integration)

The Board (Keith) has directed open sourcing as a strategic priority aligned with the org mission: *"Democratize and decentralize internet services for scientific, technology-focused, and tolerant people."*

## Your First Tasks

1. **Read the site instructions:** `org-chart/sites/open-source/site.instructions.md`
2. **Read the feature definition:** `features/forseti-open-source-initiative/feature.md`
3. **Escalate the GitHub org decision to the Board** — the question is whether to publish under `keithaumiller/` (personal) or create a new org like `forseti-community/`. This is a governance decision requiring Board approval before any public repo is created.
4. **Build your PM artifact:** a phased project schedule in `sessions/pm-open-source/artifacts/oss-project-schedule.md` — phases, owners, rough sequence (no dates).
5. **Draft your first dispatch to dev-open-source:** Phase 1 pre-publish security audit (BFG scan, secrets removal). Hold dispatch until Board answers GitHub org question.

## Known Blockers (Phase 0 — must resolve before any code goes public)
- 🔴 GitHub org decision (Board required)
- 🔴 3 RSA private keys present in `sites/forseti/keys/` — must be removed from files AND git history
- 🟡 1,813 git commits — full BFG scan needed
- 🟡 `sessions/` in copilot-hq contains internal org communications — must be excluded

## Out of Scope for You
- Selecting which technology to use (architect-copilot owns extraction tooling decisions)
- Modifying production files directly (dev-open-source executes; you own the plan)

## Escalation path
- GitHub org decision → Board (Keith)
- Git history secrets risk → architect-copilot for technical approach
- All other decisions → PM authority

