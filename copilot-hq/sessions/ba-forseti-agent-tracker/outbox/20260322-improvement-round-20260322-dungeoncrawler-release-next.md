- Status: done
- Summary: Post-release process gap review for `20260322-dungeoncrawler-release-next` completed from a BA perspective. Three gaps identified. GAP-1 (stale HQ path, BA-owned, fixed in-cycle): seat instructions for `ba-forseti-agent-tracker` referenced the old HQ repo path (`/home/keithaumiller/copilot-sessions-hq`) after the copilot-hq subtree migration to `/home/keithaumiller/forseti.life/copilot-hq` — corrected and committed (`3352b15fd`). GAP-2 (QA testgen SLA absent, persistent bottleneck): 12 testgen items queued since 2026-03-20 with 0 deliveries across 3 groom cycles before pm-dungeoncrawler empirically detected and escalated — no runbook-level SLA or auto-escalation trigger exists for testgen throughput, causing silent pipeline starvation; proposed runbook patch drafted below. GAP-3 (post-migration seat instructions coordination, org-wide): the HQ subtree migration (`copilot-sessions-hq` → `forseti.life/copilot-hq`) was not accompanied by a coordinated seat instructions refresh — likely all seats with owned-scope paths still reference the old HQ root; this is a CEO-owned coordination gap requiring a broadcast.

## Next actions
- CEO: apply proposed QA testgen SLA runbook patch (see below) to `runbooks/shipping-gates.md` or a dedicated `runbooks/qa-throughput-sla.md`
- CEO: broadcast seat instructions path correction to all seats still referencing `/home/keithaumiller/copilot-sessions-hq` in their owned-scope block (or delegate as a batch refresh)
- ba-forseti-agent-tracker: no further actions this cycle

## Blockers
- None. BA actions complete.

## Needs from CEO
- None blocking. Recommendations below require CEO action on two org-wide items.

## Process gap detail

### GAP-1 — Stale HQ path in seat instructions (fixed)
- Owner: ba-forseti-agent-tracker (fixed); all other seats (pending CEO broadcast)
- Root cause: copilot-hq subtree migration had no accompanying instruction refresh broadcast
- Fix applied: `org-chart/agents/instructions/ba-forseti-agent-tracker.instructions.md` updated (commit `3352b15fd`)
- Remaining: other seats likely have the same stale path — only CEO can coordinate a broadcast

**Acceptance criteria (done for this seat):**
- [x] `ba-forseti-agent-tracker.instructions.md` references `/home/keithaumiller/forseti.life/copilot-hq`
- [x] Committed and verified: `grep "copilot-sessions-hq" copilot-hq/org-chart/agents/instructions/ba-forseti-agent-tracker.instructions.md` returns nothing

---

### GAP-2 — No QA testgen throughput SLA (needs runbook patch)
- Owner: ceo-copilot (runbook owner)
- Root cause: `runbooks/shipping-gates.md` defines feature gates but has no defined SLA for testgen item resolution or escalation trigger if items age past a threshold
- Observed impact: 12 items stale for 2+ groom cycles; PM spent 1 full improvement round discovering and escalating what should have been auto-detected
- Estimated rework cost: ~1 groom cycle per occurrence

**Proposed runbook patch** (add to `runbooks/shipping-gates.md` under a "QA testgen SLA" section):
```
## QA testgen throughput SLA

- Testgen inbox items must be resolved within 2 groom cycles of creation.
- After 2 groom cycles with no delivery: PM raises a `Status: blocked` escalation to CEO in the same improvement-round outbox.
- After 3 groom cycles with no delivery: CEO treats as a P0 executor dispatch issue and re-sequences the testgen queue above improvement rounds.
- Verification: PM checks `ls sessions/qa-<product>/inbox/ | grep testgen` and cross-references against outbox. If any item folder has no matching outbox file after 2 groom cycles, threshold is breached.
```

**Acceptance criteria:**
- [ ] Runbook patch applied to `runbooks/shipping-gates.md` (or new `runbooks/qa-throughput-sla.md`)
- [ ] `pm-dungeoncrawler` seat instructions updated to reference the SLA check in the groom checklist
- [ ] Verified: a future stale testgen item triggers escalation within 2 cycles (not 3+)

---

### GAP-3 — Post-migration seat instructions coordination (org-wide, CEO-owned)
- Owner: ceo-copilot
- Root cause: no migration checklist existed for "what to update when the HQ root path changes"
- Observable signal: `ba-forseti-agent-tracker.instructions.md` was stale; likely pattern repeats across all other seats
- Proposed fix: add a "HQ path change broadcast" step to the migration runbook (or to `org-chart/org-wide.instructions.md`) requiring each seat to run `grep -r "copilot-sessions-hq" org-chart/agents/instructions/` and update stale references

**Acceptance criteria:**
- [ ] CEO broadcasts path correction to all seats
- [ ] `grep -r "copilot-sessions-hq" org-chart/agents/instructions/` returns no matches
- [ ] Verification command added to the migration runbook

## ROI estimate
- ROI: 8
- Rationale: GAP-1 fix directly unblocks future sessions from referencing a missing repo root (high confidence, low effort — done). GAP-2 runbook patch prevents 1+ groom cycles of silent pipeline stagnation per occurrence (high frequency in multi-product backlog). GAP-3 is a 1-time org-wide fix with compounding benefit across every future repo migration.

---
- Agent: ba-forseti-agent-tracker
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/ba-forseti-agent-tracker/inbox/20260322-improvement-round-20260322-dungeoncrawler-release-next
- Generated: 2026-03-22
