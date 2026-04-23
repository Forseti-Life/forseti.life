- Status: blocked
- Summary: dev-forseti 221501 escalation received (cycle 12, 3x superior escalation). QA run 20260422-221501 shows identical 3 failures: 404 on `/index.php/roadmap/PROJ-002`, `/PROJ-008`, `/PROJ-011`. Zero technical change since cycle 1: production has not run `git pull + drush cr`, both fixes remain staged on `origin/main` (`6f82d6e92`, `7b31fb415`), SSH is `Permission denied (publickey)`, GitHub Actions secrets (`PRIVATE_KEY`, `HOST`, `USERNAME`) remain absent. Board inbox has been updated through cycle 9 (item `20260422-needs-ceo-copilot-2-20260422-roadmap-404-deploy-blocked`). CEO endorses dev-forseti recommendation: halting further dev-forseti QA requeue cycles until Board confirms deploy has run. This is solely a human ops task.

## Next actions
- Board (Keith): SSH to `ubuntu@forseti.life` → `git pull --rebase origin main && vendor/bin/drush cr` — clears all 3 404s
- Board (Keith): restore GitHub Actions secrets (`PRIVATE_KEY`, `HOST`, `USERNAME`) — permanently eliminates this class of blocker
- After deploy confirmed: trigger QA re-run to close forseti-release-m gate

## Blockers
- Production deploy is Board-only: no SSH access from agent seats, no Actions secrets set
- Cycle 12 — identical blocker since cycle 1; no dev or CEO action remains

## Needs from Board
- Confirm when `drush cr` has been run on `ubuntu@forseti.life`
- Restore GitHub Actions secrets to prevent this class of blocker in future release cycles

## Decision needed
- Board: will you run `drush cr` manually now, or restore Actions secrets for automated deploys?

## Recommendation
- Both: SSH now (30 seconds) to unblock release-m immediately; restore secrets in parallel (5 minutes) to prevent recurrence in every future release cycle.

## ROI estimate
- ROI: 950
- Rationale: 12 blocked cycles consuming agent slots at zero ROI; a single 30-second human action unblocks the release gate. Restoring secrets eliminates the entire class permanently.
