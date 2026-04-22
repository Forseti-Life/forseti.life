- Status: blocked
- Summary: dev-forseti has now been blocked 4 consecutive cycles on the same root cause: GitHub Actions SSH deploy key is missing, so neither of the two staged fixes can reach production automatically. This escalation adds a second commit — `7b31fb415` (`hook_url_outbound_alter` in `forseti_content.module`) — on top of the `.htaccess` fix `6f82d6e92`. Both are on `origin/main`. Production requires `git pull --rebase origin main` followed by `drush cr` (the hook implementation requires Drupal cache rebuild to be discovered). Three roadmap links remain 404 on production. Board inbox item updated (`86771daf8`) to include both commits and the drush cr requirement.

## Next actions
- Board (Keith): `ssh ubuntu@forseti.life`, then `cd /home/ubuntu/forseti.life && git pull --rebase origin main && cd sites/forseti && vendor/bin/drush cr`
- Verify nav links: `curl -s https://forseti.life/ | grep roadmap` → expect `/roadmap` not `/index.php/roadmap`
- Verify 404s cleared: `curl -sI https://forseti.life/index.php/roadmap/PROJ-002` → expect `301`
- After Board confirms: dispatch qa-forseti to re-run full site audit
- Board: restore `PRIVATE_KEY` GitHub Actions secret to permanently fix automated deploys

## Blockers
- GitHub Actions `PRIVATE_KEY` secret missing — all automated production deploys failing (4th cycle)
- Production has not pulled `6f82d6e92` or `7b31fb415` — roadmap 404s and stale nav links persist

## Needs from Board
- Execute `git pull --rebase origin main && drush cr` on `ubuntu@forseti.life`
- Restore `PRIVATE_KEY` GitHub Actions secret in `Forseti-Life/forseti.life` repo settings

## Decision needed
- Will Keith execute the two production commands directly via SSH?

## Recommendation
- Direct SSH is zero-risk and takes 2 minutes. Restore the PRIVATE_KEY secret in the same session — this prevents the entire deploy-blocked pattern from recurring on every future code push.

## ROI estimate
- ROI: 40
- Rationale: 4th consecutive blocked cycle on the same deploy gap. Three live 404s on the public roadmap. Both fixes are production-ready; only SSH access stands between current state and a clean QA audit. Resolving the PRIVATE_KEY gap has high multiplier value for all future releases.
