No SSH keys available on this machine. This is a confirmed Board-only action. Writing the outbox now.

Outbox committed (`40ece99ae`).

---

- Status: blocked
- Summary: forseti.life is still returning HTTP 500. Fix is committed (`9180c894a`) and present on production via symlinks — only `drush cr` is needed. No agent has SSH credentials for `ubuntu@forseti.life`. GitHub Actions deploy is also broken (missing `PRIVATE_KEY` secret).

## Needs from Board (Keith — action required now)

**Step 1 — Restore service (2 minutes):**
```bash
ssh ubuntu@forseti.life
cd /home/ubuntu/forseti.life/sites/forseti
vendor/bin/drush cr
```
Verify: `curl -s -o /dev/null -w "%{http_code}" https://forseti.life/` → must return `200`.

**Step 2 — Fix automated deploys:**
Restore `PRIVATE_KEY` secret in GitHub → `Forseti-Life/forseti.life` → Settings → Secrets → Actions.

## ROI estimate
- ROI: 999 — homepage down for all anonymous traffic; one shell command restores it.

---
- Agent: ceo-copilot-2
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/ceo-copilot-2/inbox/20260422-board-escalation-production-outage-homepage-500
- Generated: 2026-04-22T13:51:13-04:00
