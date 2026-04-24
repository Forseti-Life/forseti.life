- Status: blocked
- Summary: QA run 20260423-001501 reports the same 3 failures as all prior cycles: `/index.php/roadmap/PROJ-002`, `/PROJ-008`, `/PROJ-011` return 404. This is cycle 15. Production still outputs `href="/index.php/roadmap"` — `drush cr` has not run, Drupal's hook registry remains stale. Both code fixes are on `origin/main` (commits `6f82d6e92` and `7b31fb415`). All dev work is complete. No further agent action is possible until a human runs `drush cr` on production.

## Next actions
- No dev actions remain
- Human command to unblock: `ssh ubuntu@forseti.life && cd /home/ubuntu/forseti.life && git pull --rebase origin main && cd sites/forseti && vendor/bin/drush cr`
- Verify after: `curl -s https://forseti.life/ | grep roadmap` must show `/roadmap` not `/index.php/roadmap`
- Orchestrator should permanently halt QA requeues for this release-m item until human confirms `drush cr` has run — 15 consecutive wasted cycles

## Blockers
- No SSH access from agent seats to `ubuntu@forseti.life`
- GitHub Actions secrets `PRIVATE_KEY`, `HOST`, `USERNAME` unset — deploy.yml cannot connect

## Needs from Supervisor
- Human must run `drush cr` on production manually, OR restore GitHub Actions SSH secrets to enable automated deploys
- See KB lesson: `knowledgebase/lessons/20260422-drush-cr-required-after-hook-registration.md`

## Decision needed
- Will human run `drush cr` manually, or restore GitHub Actions secrets?

## Recommendation
- Restore Actions secrets (`HOST`, `USERNAME`, `PRIVATE_KEY`) — one-time 5-minute task that permanently prevents this class of deploy-blocked cycles for all future releases.

## ROI estimate
- ROI: 950
- Rationale: 15 consecutive wasted cycles on a single unresolved human action. Restoring secrets eliminates this class of blocker permanently for all future releases.

---
- Agent: dev-forseti
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260423-001501-qa-findings-forseti-life-3
- Generated: 2026-04-23T00:22:29-04:00
