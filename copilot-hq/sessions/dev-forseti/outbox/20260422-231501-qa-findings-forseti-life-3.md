Identical state. This is cycle 13. Writing KB lesson to document the persistent blocker pattern, then outbox.

- Status: blocked
- Summary: QA run 20260422-231501 shows the same 3 failures as all prior cycles: `/index.php/roadmap/PROJ-002`, `/PROJ-008`, `/PROJ-011` return 404. This is cycle 13. Production still outputs `href="/index.php/roadmap"` — Drupal's URL cache has not been rebuilt because `drush cr` has not run. Both code fixes (`6f82d6e92` `.htaccess` redirect, `7b31fb415` `hook_url_outbound_alter`) are on `origin/main`. GitHub Actions SSH secrets remain unset. No further dev actions are possible. Wrote KB lesson `20260422-drush-cr-required-after-hook-registration.md` (commit `0bad70588`) to document the pattern and prevent recurrence.

## Next actions
- No dev actions remain — all code is on origin/main
- Human command to unblock: `ssh ubuntu@forseti.life && cd /home/ubuntu/forseti.life && git pull --rebase origin main && cd sites/forseti && vendor/bin/drush cr`
- After drush cr: QA re-run expected to clear all 3 failures and close release-m gate
- Orchestrator should halt QA requeues for this item until human confirms drush cr has run

## Blockers
- No SSH access from any agent seat to `ubuntu@forseti.life`
- GitHub Actions secrets `PRIVATE_KEY`, `HOST`, `USERNAME` unset — deploy.yml fails at SSH step

## Needs from Supervisor
- Human must run `drush cr` on production manually, OR restore GitHub Actions SSH secrets (`HOST`, `USERNAME`, `PRIVATE_KEY` in repo Settings → Secrets → Actions)

## Decision needed
- Will human run `drush cr` manually now, or restore Actions secrets to enable automated deploys going forward?

## Recommendation
- Restore Actions secrets (5-minute task) — permanently unblocks all future hook-dependent deploys and eliminates this entire class of repeated blocked cycles.

## ROI estimate
- ROI: 950
- Rationale: 13 consecutive wasted QA dispatch cycles on a single unresolved human action. Restoring secrets prevents recurrence for all future releases indefinitely.

---
- Agent: dev-forseti
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260422-231501-qa-findings-forseti-life-3
- Generated: 2026-04-22T23:19:49-04:00
