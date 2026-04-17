I'll start by reading the relevant files to understand the task before executing any verification steps.

## Step 1: Read inbox, dev outbox, acceptance criteria, and seat instructions

**Reading inbox command:**

```
/home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260414-unit-test-20260414-172605-impl-forseti-jobhunter-resume-version-labeli/command.md
```

Already read above. Now let me read the dev outbox and acceptance criteria.

```bash
cat /home/ubuntu/forseti.life/copilot-hq/sessions/dev-forseti/outbox/20260414-172605-impl-forseti-jobhunter-resume-version-labeling.md
```

```bash
ls /home/ubuntu/forseti.life/copilot-hq/features/ | grep -i jobhunter
```

```bash
find /home/ubuntu/forseti.life/copilot-hq/features -iname "*.md" | xargs grep -l -i "jobhunter\|job.hunter\|resume.version" 2>/dev/null
```

```bash
cat /home/ubuntu/forseti.life/copilot-hq/org-chart/agents/instructions/qa-forseti.instructions.md
```

Let me execute these now:

---

I'll use bash to do all the reads in sequence.

```bash
cat /home/ubuntu/forseti.life/copilot-hq/sessions/dev-forseti/outbox/20260414-172605-impl-forseti-jobhunter-resume-version-labeling.md 2>&1 | head -200
```

```bash
find /home/ubuntu/forseti.life/copilot-hq/features -maxdepth 2 -name "feature.md" | xargs grep -l -i "job.hun\|resume.version\|version.label" 2>/dev/null
```

```bash
cat /home/ubuntu/forseti.life/copilot-hq/org-chart/agents/instructions/qa-forseti.instructions.md 2>&1
```

```bash
cat /home/ubuntu/forseti.life/copilot-hq/org-chart/sites/forseti.life/qa-regression-checklist.md 2>&1
```

Let me actually run these now via the tool interface:

---

- Status: in_progress
- Summary: Reading dev outbox, acceptance criteria, seat instructions, and regression checklist before executing targeted verification for the forseti-jobhunter resume version labeling feature. Next concrete step: execute bash reads of all prerequisite files, then run ALLOW_PROD_QA=1 site-audit-run.sh and write the Verification Report.

## Next actions
- Read dev outbox at `sessions/dev-forseti/outbox/20260414-172605-impl

---
- Agent: qa-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260414-unit-test-20260414-172605-impl-forseti-jobhunter-resume-version-labeli
- Generated: 2026-04-17T00:30:44+00:00
