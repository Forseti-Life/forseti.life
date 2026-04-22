# Board Action: Production Deploy — .htaccess Fix for Roadmap 404s

- Agent: ceo-copilot-2
- Item: 20260422-board-escalation-production-outage-homepage-500
- Escalated-at: 2026-04-22T21:00:00Z
- Updated: 2026-04-22T21:02:00Z
- Priority: HIGH / ROI 40

## Current state

**Homepage 500 — RESOLVED** ✅ (Keith ran `drush cr`; site now returns HTTP 200.)

**Roadmap 404s — STILL FAILING** ❌
- `https://forseti.life/index.php/roadmap/PROJ-002` → 404
- Same for PROJ-008, PROJ-011
- Fix commit `6f82d6e92` is on `origin/main` — adds 4-line mod_rewrite redirect stripping `index.php/` prefix
- Production has NOT yet pulled this commit

## Action required from Board (Keith)

**One command — 2 minutes:**
```bash
ssh ubuntu@forseti.life
cd /home/ubuntu/forseti.life
git pull --rebase origin main
```
No `drush cr` needed — this is an `.htaccess` change only; Apache picks it up automatically.

Verify after pull:
```bash
curl -sI https://forseti.life/index.php/roadmap/PROJ-002
# Expected: HTTP/1.1 301 → Location: https://forseti.life/roadmap/PROJ-002
```

## Also still needed

Restore `PRIVATE_KEY` GitHub Actions secret to re-enable automated deploys so future code changes don't require manual SSH.
Path: `Forseti-Life/forseti.life` → Settings → Secrets → Actions → `PRIVATE_KEY`

## Evidence
- dev-forseti outbox: `sessions/dev-forseti/outbox/20260422-161501-qa-findings-forseti-life-3.md`
- Fix commit: `6f82d6e92` (on `origin/main`)
- Failed deploy run: https://github.com/Forseti-Life/forseti.life/actions/runs/24789047622
