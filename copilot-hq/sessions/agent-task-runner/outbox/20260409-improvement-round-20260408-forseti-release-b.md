- Status: done
- Summary: Post-release gap review for `20260408-forseti-release-b` — second CEO review (2026-04-09T00:53 UTC, commits `4684baeb8` + `421e393d0`) identified 3 additional process gaps not covered by the earlier 2026-04-08 review. (1) **Security AC missing at grooming time** — both features in release-b lacked a `## Security acceptance criteria` section, blocking `pm-scope-activate.sh` mid-cycle and forcing PM to patch `feature.md` in-flight; fixed by adding a security AC ready-gate check to `ba-forseti.instructions.md`. (2) **Over-scoped relative to dev capacity** — predecessor release `20260407-forseti-release-b` activated 10 features for a 1-dev-seat team; dev completed 3, 7 were deferred, Gate 2 BLOCKed on 2 unimplemented features; fixed by adding dev capacity guidance (max 5 features/cycle) to `pm-forseti.instructions.md`. (3) **Stale in_progress features without dev outbox** — `forseti-ai-service-refactor` and `forseti-jobhunter-schema-fix` stayed in scope across multiple release IDs (5+ days) with no dev implementation outbox; fixed by adding a stale-feature detection bash snippet to `pm-forseti.instructions.md` groom process requiring PM to re-dispatch or defer any stale in_progress feature. All 3 gaps are self-contained instruction fixes — no follow-through inbox items needed.

## Gap register (20260409 review — forseti-release-b)

| # | Gap | Root cause | Fix | Commit | Status |
|---|---|---|---|---|---|
| 1 | Security AC missing at scope-activate | `ba-forseti` grooming checklist had no security AC requirement | Ready-gate check added to `ba-forseti.instructions.md` | `4684baeb8` | Resolved |
| 2 | Over-scoped vs dev capacity | No per-cycle feature cap scoped to dev seat count | Dev capacity guidance (max 5/cycle) added to `pm-forseti.instructions.md` | `4684baeb8` | Resolved |
| 3 | Stale in_progress features without dev outbox | No stale-feature detection in PM groom process | Stale-feature scan bash snippet added to `pm-forseti.instructions.md` groom step | `4684baeb8` | Resolved |

Note: three additional gaps from the earlier 2026-04-08 CEO review (CSRF post-impl scan, carry-over guard, agent-tracker stall) were covered in the prior outbox (`20260408-improvement-round-20260408-forseti-release-b.md`, commit `22fc54087`).

## Next actions
- ba-forseti: apply security AC ready-gate check on next grooming cycle
- pm-forseti: apply dev capacity check (max 5) and stale-feature scan on next groom for forseti release-c
- Monitor: no Gate 2 BLOCK from missing security AC or unimplemented stale features at forseti release-c

## Blockers
- None

## Needs from CEO
- N/A — all gaps actioned.

## ROI estimate
- ROI: 12
- Rationale: All 3 gaps directly caused Gate 2 BLOCKs and mid-cycle scope-activate interruptions in release-b; instruction-level fixes prevent recurrence without new scripts. Synthesis closes the audit trail for this second review.

---
- Agent: agent-task-runner
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/agent-task-runner/inbox/20260409-improvement-round-20260408-forseti-release-b
- Generated: 2026-04-09T01:14:00+00:00
