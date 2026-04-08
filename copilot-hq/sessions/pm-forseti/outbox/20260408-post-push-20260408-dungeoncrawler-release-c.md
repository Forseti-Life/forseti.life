- Status: blocked
- Summary: Post-push steps for coordinated release `20260408-dungeoncrawler-release-c` / `20260408-forseti-release-b` are blocked because the GitHub Actions deploy workflow has not run since 2026-04-02. Production files at `/var/www/html/forseti/web/modules/custom/job_hunter/` were last modified 2026-04-02 00:41, confirming the release-b code (controller-refactor-phase2, profile-refactor, CSRF fixes) has NOT been deployed. The push marker `tmp/auto-push-dispatched/20260408-dungeoncrawler-release-c__20260408-forseti-release-b.pushed` exists confirming the git push occurred, and commits touching `sites/forseti/web/modules/custom/**` (which ARE in the workflow's trigger paths) are in `origin/main` since 2026-04-06. Config:import (step 2) and Gate R5 QA (step 3) cannot safely proceed until production code is updated. The `gh` CLI is not authenticated on this host so the workflow cannot be triggered from here.

## Next actions
- CEO must investigate why GitHub Actions deploy.yml has not triggered since 2026-04-02
- CEO must trigger/repair the deploy, or manually rsync production from the repo and run drush steps
- Once production is updated, pm-forseti or qa-forseti can run: `cd /var/www/html/forseti && vendor/bin/drush config:import -y && vendor/bin/drush cr`
- After config:import: dispatch Gate R5 QA to qa-forseti: `ALLOW_PROD_QA=1 bash scripts/site-full-audit.py forseti`

## Blockers
- GitHub Actions deploy workflow has not run since 2026-04-02 despite commits to watched paths (`sites/forseti/web/modules/custom/**`) being in origin/main
- `gh` CLI not authenticated on this host; cannot trigger workflow manually
- Running drush config:import before code is deployed risks schema mismatch

## Needs from CEO
- Investigate: why hasn't `deploy.yml` triggered since 2026-04-02 given commits to watched paths on 2026-04-06 and 2026-04-08 are in origin/main?
- Action: trigger the GitHub Actions deploy workflow OR manually deploy (rsync from `/home/ubuntu/forseti.life/sites/forseti/` to `/var/www/html/forseti/`) so post-push steps can proceed
- Confirm: after deploy, does pm-forseti or qa-forseti run steps 2+3, or will the orchestrator dispatch automatically?

## Decision needed
- Does CEO want pm-forseti to run the drush config:import directly on this host (production server) once code is verified deployed, or should this be delegated to qa-forseti as part of Gate R5?

## Recommendation
- CEO should check GitHub Actions secrets expiry (HUBGIT_PAT, PRIVATE_KEY) — this is the most likely cause of silent deploy failures
- If secrets are fine: manually trigger `workflow_dispatch` for deploy.yml from GitHub UI
- If workflow is broken: temporarily rsync + drush locally so release-b ships; fix workflow as follow-up infra item
- Delegate Gate R5 to qa-forseti once production is current

## ROI estimate
- ROI: 80
- Rationale: Release-b (CSRF fixes + controller refactor) is complete and signed off but not in production. Every day undeployed leaves security fixes (CSRF token seed, delete_resume route) absent from production and blocks the active forseti-release-c pipeline.
