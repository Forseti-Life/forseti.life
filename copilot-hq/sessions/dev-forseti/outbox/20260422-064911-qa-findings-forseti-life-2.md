The duplicate `path: '/'` across two modules is the root design issue. Let me fix it by removing the duplicate route from `forseti_safety_content` (which shouldn't own the homepage — `forseti_content` is the correct owner) and commit.

Now write the outbox:

---

- Status: blocked
- Summary: QA run `20260422-064911` found `https://forseti.life/` returning HTTP 500 for anonymous users. Root cause: two custom Drupal modules (`forseti_content` and `forseti_safety_content`) both registered `path: '/'`, causing ambiguous route resolution — Drupal's `ControllerResolver` throws `InvalidArgumentException: The controller for URI "/" is not callable`. Fix applied: removed the duplicate `forseti.root` route from `forseti_safety_content.routing.yml`; `forseti_content.root` is the sole owner of `/`. Committed as `9180c894a` and pushed to GitHub `main`. The fix is live in git but the Drupal router cache on the production server (`/home/ubuntu/forseti.life/sites/forseti`) has not been rebuilt. Production requires `git pull --rebase origin main` + `drush cr` to pick up the change. GitHub Actions deploy.yml also failed (run 24789047622) due to missing `PRIVATE_KEY` secret. This agent has no SSH access to the production server. The site remains down for all anonymous traffic.

## Product context
- Website: forseti.life
- Module: `forseti_safety_content` (route conflict), `forseti_content` (homepage owner)
- Role: dev-forseti (code fix applied; production cache rebuild blocked)
- Work item: QA findings run `20260422-064911`, permission violation + 500 failure on `/`

## Next actions
- Production operator: SSH to `ubuntu@forseti.life` and run:
  ```
  cd /home/ubuntu/forseti.life && git pull --rebase origin main
  cd sites/forseti && vendor/bin/drush cr
  ```
- Verify: `curl -s -o /dev/null -w "%{http_code}" https://forseti.life/` → must return `200`
- After 200 confirmed: re-run `ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh forseti-life` → verify `failures: 0`, `permission_violations: 0`
- Optional: restore `PRIVATE_KEY` GitHub Actions secret so deploy.yml can SSH automatically in future

## Blockers
- No SSH access to production server `ubuntu@forseti.life` from this agent
- GitHub Actions `PRIVATE_KEY` secret missing/invalid — deploy.yml SSH fails (run 24789047622)

## Needs from Supervisor
- Production SSH execution: `git pull --rebase origin main` + `vendor/bin/drush cr` on `ubuntu@forseti.life` at path `/home/ubuntu/forseti.life/sites/forseti`

## Decision needed
- Who will execute the 2-step production cache rebuild: human owner (Keith) directly via SSH, or CEO via another route? The `PRIVATE_KEY` secret needs to be restored in GitHub → Settings → Secrets for automated deploy to resume working.

## Recommendation
- Fastest path: human owner SSHs directly to production and runs the 2 commands — 2 minutes, site is back up immediately.
- Secondary path: restore the `PRIVATE_KEY` GitHub Actions secret, then re-trigger `deploy.yml` — automated and repeatable for all future deploys, but requires GitHub secret management access.
- Recommend the human owner does both: immediate SSH fix now + secret restore after, so automated deploy is reliable going forward.

## ROI estimate
- ROI: 999
- Rationale: Homepage is down for all anonymous traffic on forseti.life. The code fix is committed and pushed — only a production cache rebuild stands between current 500 and restored service. Every cycle of delay is lost user access.

---
- Agent: dev-forseti
- Source inbox: sessions/dev-forseti/inbox/20260422-064911-qa-findings-forseti-life-2
- Fix commit: `9180c894a`
- Deploy run (failed, missing SSH key): https://github.com/Forseti-Life/forseti.life/actions/runs/24789047622
- Generated: 2026-04-22
