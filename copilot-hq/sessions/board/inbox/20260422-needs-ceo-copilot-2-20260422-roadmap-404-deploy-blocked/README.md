# Board Action: Production Deploy — Roadmap 404 Fix (Updated)

- Agent: ceo-copilot-2
- Item: 20260422-needs-escalated-dev-forseti-20260422-171501-qa-findings-forseti.life-3
- Updated: 2026-04-22T23:12:00Z
- Priority: HIGH / ROI 40

## Current state

**Homepage 500 — RESOLVED** ✅ (HTTP 200 confirmed)
**Roadmap 404s — STILL FAILING** ❌ (3 live 404s on `/index.php/roadmap/PROJ-*`)

Two fixes are on `origin/main`, neither yet deployed to production:
- `6f82d6e92` — `.htaccess` R=301 redirect (belt)
- `7b31fb415` — `hook_url_outbound_alter` in `forseti_content.module` stripping `index.php/` prefix from all generated nav URLs (suspenders — the real fix; requires `drush cr`)

This is dev-forseti's **4th consecutive blocked cycle** on the same deploy blocker.

## Action required from Board (Keith)

**Two commands — 2 minutes:**
```bash
ssh ubuntu@forseti.life
cd /home/ubuntu/forseti.life && git pull --rebase origin main
cd sites/forseti && vendor/bin/drush cr
```

Verify nav links after deploy:
```bash
curl -s https://forseti.life/ | grep roadmap
# Expected: /roadmap (NOT /index.php/roadmap)
```

Verify 404s cleared:
```bash
curl -sI https://forseti.life/index.php/roadmap/PROJ-002
# Expected: HTTP/1.1 301 → Location: /roadmap/PROJ-002
```

## Also still needed

Restore `PRIVATE_KEY` GitHub Actions secret:
`Forseti-Life/forseti.life` → Settings → Secrets → Actions → `PRIVATE_KEY`

This permanently fixes the automated deploy pipeline and prevents this class of blocker from recurring.

## Evidence
- dev-forseti outbox: `sessions/dev-forseti/outbox/20260422-171501-qa-findings-forseti.life-3.md`
- Commits on `origin/main`: `6f82d6e92`, `7b31fb415`
- Failed deploy run: https://github.com/Forseti-Life/forseti.life/actions/runs/24789047622
- Status: pending
