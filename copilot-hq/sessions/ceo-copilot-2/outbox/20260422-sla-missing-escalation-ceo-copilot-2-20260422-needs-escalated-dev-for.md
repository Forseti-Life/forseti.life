- Status: done
- Summary: SLA breach `missing-escalation: ceo-copilot-2` was firing because my previous blocked outbox (`20260422-needs-escalated-dev-forseti-20260422-161501-qa-findings-forseti-life-3.md`) had no corresponding board inbox item. The existing board item covered the earlier homepage-500 outbox, not this one. Created `sessions/board/inbox/20260422-needs-ceo-copilot-2-20260422-roadmap-404-deploy-blocked/README.md` with the full Board action request (production `git pull` for commit `6f82d6e92` + `PRIVATE_KEY` GitHub Actions secret restore). Commit `292ea9b58`. SLA script no longer reports `missing-escalation` for `ceo-copilot-2`. Underlying blocker remains: production has not yet pulled the `.htaccess` fix; roadmap 404s are still live.

## Next actions
- Board (Keith): `ssh ubuntu@forseti.life`, then `cd /home/ubuntu/forseti.life && git pull --rebase origin main`
- Verify: `curl -sI https://forseti.life/index.php/roadmap/PROJ-002` → must return `301`
- Board: restore `PRIVATE_KEY` GitHub Actions secret to re-enable automated deploys permanently
- CEO: after Board confirms pull, dispatch qa-forseti to re-run full site audit to close out the 3 QA failures

## Blockers
- Production has not pulled commit `6f82d6e92` — roadmap 404s remain live
- GitHub Actions `PRIVATE_KEY` secret missing — all automated deploys broken

## Needs from Board
- Execute `git pull --rebase origin main` at `/home/ubuntu/forseti.life/` on the production server
- Restore `PRIVATE_KEY` in GitHub Actions secrets

## Decision needed
- Will Keith execute the production `git pull` via direct SSH, or is there another deploy path available?

## Recommendation
- Direct SSH `git pull` is zero-risk for a `.htaccess`-only change and restores all roadmap links in under 2 minutes. Restore the PRIVATE_KEY in the same session to prevent this class of blocker from recurring on every future code push.

## ROI estimate
- ROI: 30
- Rationale: SLA compliance item is resolved (breach cleared). The underlying production deploy gap is tracked at ROI 40 in the board inbox and remains the active blocker for the dev-forseti QA cycle.
