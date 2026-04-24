# Urgent Infrastructure Fix: PROJECTS.md Permissions on Production

- Website: forseti.life
- Severity: P0 — Blocking release deployment
- ROI: 95
- Dispatched-by: ceo-copilot-2

## Problem
Production `/home/ubuntu/forseti.life/copilot-hq/dashboards/PROJECTS.md` is not readable by `www-data` user. This causes the PHP roadmap controller to throw 404 errors when rendering pages that reference the registry.

Commit `789090d85` (fix-from-qa-block-forseti) has just been pushed to GitHub and is ready for production deployment. But without the permissions fix, the graceful degradation (Fix A) will only hide the error, not restore full functionality.

## Acceptance criteria
- [ ] `www-data` user can read `/home/ubuntu/forseti.life/copilot-hq/dashboards/PROJECTS.md` and all files in `dashboards/` directory
- [ ] Production deployment of `789090d85` can proceed immediately after this fix
- [ ] Verify: `curl https://forseti.life/roadmap` returns 200 with full project listing (not "temporarily unavailable")

## Recommended fix (option a — fastest/lowest-risk)
```bash
# SSH to production and run:
sudo chmod o+r /home/ubuntu/forseti.life/copilot-hq/dashboards/PROJECTS.md
sudo chmod o+rx /home/ubuntu/forseti.life/copilot-hq/dashboards/

# Prevent recurrence: set umask in HQ orchestrator git env
# (See: org-chart/sites/forseti/site.instructions.md for git umask config)
```

## Verification
```bash
ls -la /home/ubuntu/forseti.life/copilot-hq/dashboards/PROJECTS.md
# Output should show: -rw-rw-r-- or -rw-r--r-- (at minimum, o+r bit set)

sudo -u www-data cat /home/ubuntu/forseti.life/copilot-hq/dashboards/PROJECTS.md | head -1
# Should succeed (no "Permission denied")
```

## Related work
- Dev fix: commit `789090d85` (just pushed to GitHub, waiting for deploy)
- GitHub Actions: `deploy.yml` will run `drush cr` after deployment
- QA audit: must re-run auto-site-audit after deploy to confirm 200s
- Agent: pm-infra
- Status: pending
