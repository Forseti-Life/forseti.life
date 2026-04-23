- Status: blocked
- Summary: QA run 20260422-191501 shows the same 3 failures (404 on `/index.php/roadmap/PROJ-002`, `/PROJ-008`, `/PROJ-011`) that have persisted across 6+ consecutive cycles. Both code fixes are confirmed on `origin/main` (commits `6f82d6e92` and `7b31fb415`) and have been there since earlier today. Production has NOT pulled them — `curl https://forseti.life/` still returns `href="/index.php/roadmap"`. GitHub Actions deploy.yml fails with `can't connect without a private SSH key or password` because all three required secrets (`PRIVATE_KEY`, `HOST`, `USERNAME`) are absent from the repo's Actions secrets. No automated deploy path is available. All dev-side work is complete; this is a pure infra/access blocker.

## Next actions
- **Human (Board)**: add `HOST`, `USERNAME`, and `PRIVATE_KEY` secrets to GitHub Actions repo secrets (Settings → Secrets → Actions), then re-run the "Deploy Forseti Site to Production" workflow. OR SSH directly to `ubuntu@forseti.life` and run: `cd /home/ubuntu/forseti.life && git pull --rebase origin main && cd sites/forseti && vendor/bin/drush cr`
- After deploy: verify with `curl -s https://forseti.life/ | grep roadmap` — must show `/roadmap` not `/index.php/roadmap`.
- After deploy: QA re-run to close release-m gate.
- dev-infra: enable `mod_rewrite` permanently — `sudo a2enmod rewrite && sudo systemctl reload apache2` — so this class of failure cannot recur.

## Blockers
- GitHub Actions repo secrets `PRIVATE_KEY`, `HOST`, `USERNAME` are all unset. Deploy workflow fails at SSH connection step with: `Error: can't connect without a private SSH key or password` (run 24789047622).
- No other automated path exists to run `git pull + drush cr` on production from this seat.
- This is the 6th+ consecutive blocked cycle on this exact deploy blocker. Per matrix policy (Missing access/credentials/environment path, >1 cycle), this has already been escalated to the Board.

## Decision needed
- Human owner must choose: (a) add GitHub Actions secrets and trigger deploy.yml, OR (b) SSH to production manually and run `git pull --rebase origin main && vendor/bin/drush cr`.

## Recommendation
- Option (b) is fastest: one SSH command. Option (a) fixes the automated deploy path permanently but requires locating and re-adding the SSH key. Recommend doing both: SSH manually now to unblock, then add Actions secrets to restore automation.

## Needs from CEO
- Confirm Board escalation has been received and that human owner has been informed of required manual action.
- If Board is not going to act on this within 24 hours, confirm whether release-m should be held or whether QA gate should be bypassed (policy decision, not dev-forseti scope).

## ROI estimate
- ROI: 200
- Rationale: This is a production regression blocking release close. Three QA failures have persisted for 6+ cycles solely because production can't pull already-staged fixes. Resolving the deploy blocker unblocks the entire release.
