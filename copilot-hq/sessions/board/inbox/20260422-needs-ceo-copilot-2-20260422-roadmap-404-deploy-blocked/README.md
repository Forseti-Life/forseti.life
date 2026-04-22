# Board Action: Production Git Pull — Roadmap 404 Fix

- Agent: ceo-copilot-2
- Escalated agent: ceo-copilot-2
- Escalated item: 20260422-needs-escalated-dev-forseti-20260422-161501-qa-findings-forseti-life-3
- Escalated-at: 2026-04-22T22:12:00Z
- Priority: HIGH / ROI 40

## Current state

**Homepage 500 — RESOLVED** ✅

**Roadmap 404s — STILL FAILING** ❌
- `https://forseti.life/index.php/roadmap/PROJ-002` → 404
- Same for PROJ-008, PROJ-011
- Fix commit `6f82d6e92` is on `origin/main` (`.htaccess` mod_rewrite redirect stripping `index.php/` prefix)
- Production has NOT yet pulled this commit

**Root blocker:** GitHub Actions `PRIVATE_KEY` SSH secret missing — all automated deploys failing (run 24789047622).

## Action required from Board (Keith)

**Step 1 — Deploy the .htaccess fix (2 minutes, zero risk):**
```bash
ssh ubuntu@forseti.life
cd /home/ubuntu/forseti.life
git pull --rebase origin main
```
No `drush cr` needed — `.htaccess` change only; Apache picks it up immediately.

Verify after pull:
```bash
curl -sI https://forseti.life/index.php/roadmap/PROJ-002
# Expected: HTTP/1.1 301 → Location: /roadmap/PROJ-002
```

**Step 2 — Fix automated deploys (prevents all future blockers of this class):**
Restore `PRIVATE_KEY` secret in:
`Forseti-Life/forseti.life` → Settings → Secrets → Actions → `PRIVATE_KEY`

Then re-trigger `deploy.yml` to verify automation works.

## Evidence
- CEO outbox: `sessions/ceo-copilot-2/outbox/20260422-needs-escalated-dev-forseti-20260422-161501-qa-findings-forseti-life-3.md`
- dev-forseti outbox: `sessions/dev-forseti/outbox/20260422-161501-qa-findings-forseti-life-3.md`
- Fix commit: `6f82d6e92` (on `origin/main`)
- Failed deploy run: https://github.com/Forseti-Life/forseti.life/actions/runs/24789047622
