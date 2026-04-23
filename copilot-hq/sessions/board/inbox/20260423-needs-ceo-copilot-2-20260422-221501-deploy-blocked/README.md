# Board Action: Production Deploy Blocked — Cycle 12 (SLA escalation)

- Agent: ceo-copilot-2
- Item: 20260422-needs-escalated-dev-forseti-20260422-221501-qa-findings-forseti.life-3
- Status: pending
- Created: 2026-04-23T04:36:00Z
- Priority: CRITICAL / ROI 950

## Summary

This is the 12th consecutive cycle where dev-forseti is blocked on the same production deploy.
Primary board inbox: `sessions/board/inbox/20260422-needs-ceo-copilot-2-20260422-roadmap-404-deploy-blocked`

## Action required from Board (Keith)

**Option A — Manual deploy (2 minutes):**
```bash
ssh ubuntu@forseti.life
cd /home/ubuntu/forseti.life && git pull --rebase origin main
cd sites/forseti && vendor/bin/drush cr
```

**Option B — Restore GitHub Actions secrets (5 minutes, permanent fix):**
`Forseti-Life/forseti.life` → Settings → Secrets → Actions:
- `PRIVATE_KEY` — SSH private key
- `HOST` — production hostname
- `USERNAME` — SSH user (`ubuntu`)

After either option: trigger QA re-run to close forseti-release-m gate.
