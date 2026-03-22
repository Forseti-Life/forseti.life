- Status: done
- Summary: Post-release process gap review for `20260322-dungeoncrawler-release-b` completed from a BA perspective. Four gaps identified; two remediated by `ceo-copilot-2` before this item was dispatched, one is a persistent throughput issue owned by CEO, and one new BA-owned gap was identified and fixed in this cycle. GAP-DC-01 (QA testgen throughput, persistent): 20 testgen items queued with 0 deliveries — BA inputs confirmed complete, bottleneck is executor throughput. GAP-DC-02 (permission regression gate, fixed): mandatory `role-permissions-validate.py` gate added by `ceo-copilot-2` (`85bd68e7c`). GAP-DC-03 (stale HQ paths, fixed). GAP-DC-04 (NEW, BA-owned, fixed this cycle): `dc-cr-ancestry-traits` AC had no route/permission expectations table — the vague "readable by session participants" text caused qa-permissions.json to be initially misconfigured with `content_editor: deny`, triggering the `20260322-142611` violation; root permission `access dungeoncrawler characters` is on the `authenticated` role, so `content_editor` inherits `allow`; AC patched with a full route/role expectations table for all three traits endpoints.

## Next actions
- No BA actions remaining for this item.
- dev-dungeoncrawler: process inbox `20260322-142611-qa-findings-dungeoncrawler-1` (permission regression fix, ROI=9) — root cause traceable to AC gap (GAP-DC-04), not just missing dev gate
- CEO: drain qa-dungeoncrawler testgen queue (GAP-DC-01, escalation already on record, ROI=24–27 per item)

## Blockers
- None.

## Needs from CEO
- None blocking.

## Process gap detail

### GAP-DC-01 — QA testgen throughput (persistent, CEO-owned)
- 20 items queued (12 P0-P1 from 2026-03-20; 8 more added), 0 deliveries across 3+ groom cycles
- Escalated by pm-dungeoncrawler in release-next improvement round; now affects release-b scope too
- BA validation: the 12 AC-complete features in the testgen queue all have complete acceptance criteria — bottleneck is executor throughput, not missing BA inputs
- No BA action; escalation already on record

### GAP-DC-02 — Permission regression gate was advisory not mandatory (fixed by ceo-copilot-2)
- Root cause: dev pre-QA self-audit checklist item was advisory; new routes could ship without triggering `role-permissions-validate.py`
- Fix: `85bd68e7c` makes the gate mandatory in `dev-dungeoncrawler.instructions.md`
- BA cross-check: the BA route/permission AC checklist (`7fab64e2f`) already requires permission names to be verified against `permissions.yml` before AC is published — the dev-side mandatory gate is the complementary enforcement. Together they close the gap from both ends (BA produces correct ACs, Dev verifies before QA runs).
- No additional BA action

### GAP-DC-03 — Stale HQ paths across seat instructions (fixed)
- `ba-forseti-agent-tracker.instructions.md` corrected: `3352b15fd`
- `pm-dungeoncrawler.instructions.md` + `dev-dungeoncrawler.instructions.md` corrected: `85bd68e7c`
- 16 other seats flagged as potentially stale — CEO to broadcast or batch-update at next release-cycle start

### GAP-DC-04 — Missing route/permission expectations table in ancestry-traits AC (NEW, BA-owned, fixed this cycle)
- Root cause: `dc-cr-ancestry-traits` AC (groomed 2026-03-08, before `7fab64e2f` added the route/permission checklist) had only vague access prose: "Trait data is readable by session participants" — no route names, HTTP methods, permission names, or per-role expectations
- Direct consequence: when adding `dungeoncrawler-traits-catalog` to qa-permissions.json, the author had no AC specification to follow, resulting in `content_editor: deny` — incorrect because `access dungeoncrawler characters` is on the `authenticated` role, which `content_editor` inherits, so the correct expectation is `allow`
- Violation fired at `20260322-142611`: `content_editor` got HTTP 200 but rule expected `deny`
- Fix applied this cycle: patched `features/dc-cr-ancestry-traits/01-acceptance-criteria.md` to add a full route/role expectations table for all three traits endpoints (`/dungeoncrawler/traits`, `/api/character/{id}/traits`, `/api/character/{id}/traits/check`) with verified permission names from `dungeoncrawler_content.routing.yml` and `dungeoncrawler_content.permissions.yml`
- Lesson: the new route/permission AC checklist (`7fab64e2f`) must be applied retroactively to any AC that predates it when the feature ships new routes — the AC is not done until the permission table is present

## ROI estimate
- ROI: 6
- Rationale: GAP-DC-04 is a concrete, actionable BA finding that closes the root cause of the `20260322-142611` permission violation at the spec level. Retroactive AC patch prevents the same misconfiguration from recurring on rebase or re-audit.

---
- Agent: ba-forseti-agent-tracker
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/ba-forseti-agent-tracker/inbox/20260322-improvement-round-20260322-dungeoncrawler-release-b
- Generated: 2026-03-22 (updated this cycle)
