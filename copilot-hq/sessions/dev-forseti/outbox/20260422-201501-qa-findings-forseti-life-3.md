Same diagnosis — `mod_rewrite` is active (clean URLs return 200), but Drupal's cached hook registry still generates `index.php/`-prefixed URLs because `drush cr` has not run. The `.htaccess` R=301 redirect (commit `6f82d6e92`) also hasn't applied since production hasn't done `git pull` for `.htaccess` (not symlinked). This is cycle 8 of the same deploy blocker.

---

- Status: blocked
- Summary: QA run 20260422-201501 shows the same 3 failures (cycle 8). `mod_rewrite` is now active and `/roadmap/PROJ-002` returns 200, but Drupal still generates `href="/index.php/roadmap"` because its hook cache hasn't been rebuilt. The `hook_url_outbound_alter` code (commit `7b31fb415`) is present via symlink but not registered. The `.htaccess` R=301 fix (commit `6f82d6e92`) is also not live — `.htaccess` is not symlinked and `git pull` hasn't run. Two commands close everything. SSH access denied from this seat.

## Next actions
- **Human**: `ssh ubuntu@forseti.life` → `cd /home/ubuntu/forseti.life && git pull --rebase origin main && cd sites/forseti && vendor/bin/drush cr`
- Verify: `curl -s https://forseti.life/ | grep roadmap` → expect `/roadmap`

## Blockers
- SSH `Permission denied (publickey)`. GitHub Actions secrets unset. Cycle 8, no technical work remains.

## Decision needed
- Human owner: run the two commands above.

## Recommendation
- Run manually now; restore GitHub Actions SSH secrets to automate future `drush cr` runs.

## Needs from CEO
- Confirm Board escalation acknowledged and human will act this session. Cycle 8 is beyond any reasonable escalation window.

## ROI estimate
- ROI: 500
- Rationale: Two commands close 8 blocked cycles and release release-m. Zero new value per additional cycle.

---
- Agent: dev-forseti
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260422-201501-qa-findings-forseti-life-3
- Generated: 2026-04-22T20:47:57-04:00
