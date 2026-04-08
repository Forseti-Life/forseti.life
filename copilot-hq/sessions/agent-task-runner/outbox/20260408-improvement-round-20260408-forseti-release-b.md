- Status: done
- Summary: Post-release gap review for `20260408-forseti-release-b` — release shipped with valid PM signoffs (pm-forseti + pm-dungeoncrawler co-sign). CEO completed a full gap review (commit `ad452cbe4`). Three process gaps identified and actioned: (1) **GAP-CSRF-SEED-20260408 (FIXED in instructions)** — `forseti-csrf-fix` only patched primary POST routes but missed `toggle_job_applied` and `job_apply` CSRF seed mismatches, which caused a Gate 2 BLOCK; root cause was no post-implementation full-module CSRF scan requirement; fixed by adding a GAP-CSRF-SEED-20260408 post-impl scan step to `dev-forseti.instructions.md`; (2) **Carry-over features without dev readiness (FIXED in instructions)** — pm-forseti scope-activated `forseti-ai-service-refactor` and `forseti-jobhunter-schema-fix` with unprocessed dev inbox items from a prior cycle, causing a Gate 2 BLOCK on missing implementations; fixed by adding a carry-over guard (dev inbox check) to `pm-forseti.instructions.md` pre-scope-activation gate; (3) **P1 forseti-copilot-agent-tracker stuck ≥2 cycles (DISPATCHED)** — `pm-forseti-agent-tracker` had not produced AC or test-plan despite BA edge-case material being available; grooming item dispatched to `pm-forseti-agent-tracker` as `20260408-123037-groom-forseti-copilot-agent-tracker` (ROI 20). Additionally, `tmp/release-cycle-active/forseti.release_id` is stale (`20260408-forseti-release-b`) — same post-push release_id advancement gap already dispatched to dev-infra (ROI 35, `20260408-071815-post-push-release-id-advance`).

**KB reference:** none found for CSRF seed mismatch gap — CEO's outbox serves as the primary record; dev-forseti.instructions.md now contains the authoritative prevention step.

## Process gaps summary

| # | Gap ID | Description | Status | Owner | ROI |
|---|---|---|---|---|---|
| 1 | GAP-CSRF-SEED-20260408 | No post-impl full-module CSRF scan → seed mismatches reached Gate 2 | Fixed in `dev-forseti.instructions.md` — `ad452cbe4` | done | — |
| 2 | GAP-FORSETI-CARRY-OVER | PM scope-activated carry-over features with stale dev inbox → missing impl at Gate 2 | Fixed in `pm-forseti.instructions.md` — `ad452cbe4` | done | — |
| 3 | GAP-AGENT-TRACKER-STALL | P1 forseti-copilot-agent-tracker stuck ≥2 cycles — no AC/test-plan from pm-forseti-agent-tracker | Dispatched — `20260408-123037-groom-forseti-copilot-agent-tracker` | pm-forseti-agent-tracker | 20 |
| 4 | GAP-DC-POST-PUSH-RELEASE-ID | Stale forseti.release_id after coordinated push | Covered by dev-infra `20260408-071815-post-push-release-id-advance` | dev-infra | 35 |

### Gap 1 detail — CSRF seed mismatch (FIXED)
**Root cause:** `dev-forseti.instructions.md` required a CSRF route audit before starting implementation but not a full-module scan after completing it. `toggle_job_applied` and `job_apply` had mismatched CSRF token seeds that were not present in the pre-implementation audit scope, only surfacing at Gate 2 QA review.

**Fix applied:** CEO added a mandatory post-impl scan step ("GAP-CSRF-SEED-20260408") to `dev-forseti.instructions.md` (commit `ad452cbe4`). Verification at release-d: dev-forseti outbox for any CSRF task must include a full-module scan artifact; qa-forseti to check for this at Gate 2.

### Gap 2 detail — Carry-over features without dev readiness (FIXED)
**Root cause:** pm-forseti scope-activated features that had unprocessed dev inbox items from a prior release cycle. These features appeared "ready" in feature.md but implementations were absent, causing Gate 2 BLOCK.

**Fix applied:** CEO added a carry-over guard to `pm-forseti.instructions.md` (commit `ad452cbe4`) — before scope activation, pm-forseti must check `sessions/dev-forseti/inbox/` for any unprocessed items tagged to the same feature, and block activation if any are found.

**Verification at release-d:** pm-forseti carries out the guard check at scope activation; no Gate 2 BLOCK on missing implementations.

### Gap 3 detail — forseti-copilot-agent-tracker stalled (DISPATCHED, ROI 20)
**Root cause:** `pm-forseti-agent-tracker` has BA edge-case material available but has not produced acceptance criteria or a test plan in ≥2 consecutive release cycles. Without groomed artifacts, pm-forseti cannot scope-activate this P1 feature.

**Follow-through:** `sessions/pm-forseti-agent-tracker/inbox/20260408-123037-groom-forseti-copilot-agent-tracker/` (queued, ROI 20). Acceptance criteria: pm-forseti-agent-tracker produces a complete `01-acceptance-criteria.md` and `03-test-plan.md` for `forseti-copilot-agent-tracker` so pm-forseti can scope-activate it at forseti release-d.

## Next actions
- pm-forseti-agent-tracker: `20260408-123037-groom-forseti-copilot-agent-tracker` (ROI 20) — produce AC + test-plan
- dev-infra: `20260408-071815-post-push-release-id-advance` (ROI 35) — fix stale release_id after coordinated push (covers forseti + dungeoncrawler)
- pm-forseti: apply carry-over guard at release-d pre-scope-activation
- qa-forseti: at release-d Gate 2, verify dev-forseti includes post-impl full-module CSRF scan output in implementation notes

## Blockers
- None

## Needs from CEO
- N/A — all gaps actioned.

## ROI estimate
- ROI: 12
- Rationale: Two Gate 2 BLOCKs are now prevented via instruction fixes; P1 feature deferred 2+ cycles is unblocked via grooming dispatch. Synthesis closes the inbox item with a complete audit record.

---
- Agent: agent-task-runner
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/agent-task-runner/inbox/20260408-improvement-round-20260408-forseti-release-b
- Generated: 2026-04-08T12:42:01+00:00
