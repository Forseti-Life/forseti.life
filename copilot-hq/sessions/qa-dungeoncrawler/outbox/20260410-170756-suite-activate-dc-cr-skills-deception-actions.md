# QA Suite Activation: dc-cr-skills-deception-actions

- Status: done
- Summary: Activated `dc-cr-skills-deception-actions` suite. Added `dc-cr-skills-deception-actions-phpunit` to `suite.json` with 26 TCs — 18 immediately activatable and 8 deferred (5 on `dc-cr-conditions`, 2 on `dc-cr-equipment-system`, 1 split). Activatable TCs cover: Create a Diversion action cost/traits/bonus mechanics (TC-DEC-01–03,07); Impersonate activity type, passive vs active observer resolution, and Critical Failure identity-reveal (TC-DEC-08,11–13); all 5 Lie TCs (secret check, multi-target per-DC comparison, failure resistance bonus, per-conversation scope, GM recheck — TC-DEC-14–18); Feint action/traits/proficiency/reach gates and explicit out-of-reach error path (TC-DEC-19–21,25); ACL regression (TC-DEC-26). Deferred TCs cover Diversion Hidden/flat-footed state transitions (DC-DEC-04–06) and Feint flat-footed outcomes (TC-DEC-22–24) pending `dc-cr-conditions`, plus Impersonate disguise kit inventory checks (TC-DEC-09–10) pending `dc-cr-equipment-system`. Suite validated OK. Committed `3fda7724d`.

## Verification evidence

| Item | Result |
|---|---|
| Suite id added | `dc-cr-skills-deception-actions-phpunit` |
| Total TCs | 26 (18 immediately activatable, 8 deferred) |
| required_for_release TCs | 18 (deferred TCs set false) |
| qa-permissions.json rule | `dc-cr-skills-deception-actions-acl-regression` |
| Suite validate | OK (5 manifests) |
| Commit | `3fda7724d` |

## PM notes flagged

1. **TC-DEC-02 Diversion method selection:** AC says "manipulate OR auditory+linguistic+mental depending on method." Confirm whether method (gesture vs vocalization) is a player input at resolution time or determined by AC-type on the character sheet. Automation needs a deterministic input.
2. **TC-DEC-03 Diversion bonus duration:** AC says "for 1 minute after attempting." Confirm: 1 minute = 10 combat rounds (6-second rounds) or an exploration-time window? Need scalar for round-count tracking.
3. **TC-DEC-10 Impersonate without disguise kit:** AC says "–2 or blocked per GM." Recommend system default = –2 circumstance penalty with a GM-configurable hard-block flag. PM to confirm this default.

## Deferred TCs summary

| TC | Dependency | Reason |
|---|---|---|
| TC-DEC-04 | `dc-cr-conditions` | Diversion success → Hidden condition tracking |
| TC-DEC-05 | `dc-cr-conditions` | Hidden → Observed revert on non-Hide/Sneak/Step actions |
| TC-DEC-06 | `dc-cr-conditions` | Strike while Hidden: target flat-footed + attacker becomes Observed |
| TC-DEC-09 | `dc-cr-equipment-system` | Impersonate: disguise kit inventory presence check |
| TC-DEC-10 | `dc-cr-equipment-system` | Impersonate without kit: –2 penalty gate |
| TC-DEC-22 | `dc-cr-conditions` | Feint Crit Success: flat-footed full turn |
| TC-DEC-23 | `dc-cr-conditions` | Feint Success: flat-footed next attack only |
| TC-DEC-24 | `dc-cr-conditions` | Feint Crit Fail: attacker flat-footed |

## Next actions
- Awaiting Dev implementation; run `dc-cr-skills-deception-actions-phpunit` when implementation ships.
- 5 condition-deferred TCs to activate when `dc-cr-conditions` ships.
- 2 equipment-deferred TCs to activate when `dc-cr-equipment-system` ships.

## Blockers
- None

## ROI estimate
- ROI: 17
- Rationale: 18 activatable TCs unblock Dev on all Deception action/trait/proficiency/bonus logic; deferred boundary with two dependencies clearly documented to prevent future investigation cycles.

---
- Agent: qa-dungeoncrawler
- Source inbox: sessions/qa-dungeoncrawler/inbox/20260410-170756-suite-activate-dc-cr-skills-deception-actions
- Generated: 2026-04-11
