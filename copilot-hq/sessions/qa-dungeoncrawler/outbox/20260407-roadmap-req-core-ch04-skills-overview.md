- Status: done
- Summary: Verified roadmap REQs 1551–1601 (3 sections: Chapter Overview, General Skill Actions, Skill Table 4-1) against the production DungeonCrawler codebase. Verdict: BLOCK. Three clean PASS: REQ 1551 (all 17 skills mapped to ability scores in CharacterCalculator::SKILLS), REQ 1555 (skill check formula d20+ability_mod+proficiency_bonus+item_bonus fully implemented in calculateSkillCheck()), REQ 1599 (all 17 skills in the Skill Table). Three partials: skill increase infrastructure wired (CharacterLevelingService::submitSkillIncrease() advances rank by one step), earn_income stub registered but not implemented, and processEscape() accepts a caller-supplied skill_bonus. 45 of 51 REQs are unimplemented: expert→master rank ceiling (level ≥ 7) and master→legendary (level ≥ 15) are not enforced in submitSkillIncrease(); trained-only gating is absent (calculateSkillCheck() computes rank but returns no error for untrained-attempting-trained actions); secret trait flag is not honored (numerical result always returned); armor check penalty is not applied to Str/Dex skill checks; skill DC (10 + skill mod) has no service method; activity traits are unenforced; and all General Skill Actions are missing or stub-only (Recall Knowledge, Subsist, Decipher Writing, Identify Magic, Learn a Spell, Earn Income full implementation).

## Next actions
- PM: two HIGH isolated fixes can be sent to dev immediately as small items — rank ceiling enforcement in CharacterLevelingService::submitSkillIncrease() (REQs 1563, 1564: guard expert→master at level 7, master→legendary at level 15)
- PM: secret trait flag (REQ 1568) should be added to calculateSkillCheck() return as a low-effort service change — needed before Recall Knowledge, Decipher Writing, and similar secret actions can be verified
- PM: existing suggested feature `dc-cr-skills-recall-knowledge` covers REQs 1591–1594 (high-value, untrained, 8+ applicable skills)
- PM: `calculateSkillDC()` method + `SIMPLE_DC_BY_RANK` const are single-file additions to CharacterCalculator with no downstream risk — recommend bundling into any next skills sprint
- PM: trained-only gating (REQ 1554, 1600) and armor check penalty (REQ 1566) are CharacterCalculator changes; suggest bundling as `dc-cr-skills-calculator-hardening`
- Awaiting next inbox dispatch from pm-dungeoncrawler

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 32
- Rationale: Completes the full ch04 Skills chapter audit (REQs 1551–1748 now all verified across three dispatches). Two HIGH defects (rank ceiling enforcement) are near-zero effort fixes that prevent players from illegally reaching expert/master rank early — PM can dispatch these immediately. Secret trait gap is blocking all secret-roll skill actions (Recall Knowledge, Decipher Writing, Gather Information, etc.).

---

## Evidence
- Artifact: `sessions/qa-dungeoncrawler/artifacts/20260407-roadmap-req-core-ch04-skills-overview/verification-report.md`
- Commit: `4d086d00b`

### Verdict summary by section

| Section | PASS | PARTIAL | BLOCK | Verdict |
|---|---|---|---|---|
| Chapter Overview (1551–1571) | 2 | 2 | 15 | BLOCK |
| General Skill Actions (1572–1598) | 0 | 1 (earn_income stub) | 26 | BLOCK |
| Skill Table (1599–1601) | 1 | 1 | 1 | BLOCK |

Overall: BLOCK. 3 PASS, 3 PARTIAL, 45/51 REQs unimplemented.

### Highest-priority gaps

| Gap | REQs | Severity |
|---|---|---|
| Expert→master ceiling not enforced (level ≥ 7) | 1563 | HIGH |
| Master→legendary ceiling not enforced (level ≥ 15) | 1564 | HIGH |
| Trained-only gating absent | 1554, 1600 | HIGH |
| Secret trait not honored in calculateSkillCheck() | 1568 | HIGH |
| All General Skill Actions absent (Recall Knowledge, Subsist, Decipher Writing, Identify Magic, Learn a Spell) | 1574–1598 | HIGH |
| Armor check penalty absent | 1566–1567 | MEDIUM |
| Skill DC method (10 + skill mod) missing | 1556 | MEDIUM |
