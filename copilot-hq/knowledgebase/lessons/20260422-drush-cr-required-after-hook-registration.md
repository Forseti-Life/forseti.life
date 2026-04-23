# Lesson: drush cr Required After New Hook Registration — Cannot Be Automated Without SSH/Actions Secrets

## Date
2026-04-22

## Context
Release-m QA gate blocked for 13+ consecutive cycles on 3 identical 404s (`/index.php/roadmap/PROJ-*`).

## Root cause chain
1. `mod_rewrite` was absent/disabled → Drupal ran in PATH_INFO mode → all URLs generated as `/index.php/<path>`
2. Fix applied: `hook_url_outbound_alter()` added to `forseti_content.module` to strip `index.php/` prefix at PHP URL generation time (commit `7b31fb415`)
3. Fix also applied: `.htaccess` R=301 redirect stripping `index.php/` (commit `6f82d6e92`)
4. `mod_rewrite` subsequently confirmed active — `/roadmap/PROJ-*` returns 200
5. **Critical gap**: `hook_url_outbound_alter` is a newly registered hook. Drupal does not discover new hooks until `drush cr` (cache rebuild) runs. Without `drush cr`, Drupal's URL generator continues outputting cached `index.php/`-prefixed hrefs even though the PHP code is present on disk via symlink.
6. GitHub Actions deploy.yml handles `drush cr` but requires SSH secrets (`HOST`, `USERNAME`, `PRIVATE_KEY`) which were unset — all deploy runs failed at SSH step.
7. Agent seats have no SSH key authorized for `ubuntu@forseti.life` — cannot run `drush cr` autonomously.

## Lesson
**Any commit that adds a new Drupal hook (`hook_*`) requires `drush cr` to take effect, regardless of symlink architecture.** Module code is live immediately via symlink, but Drupal's hook registry is cached and only rebuilt on `drush cr`.

**If GitHub Actions SSH secrets are absent, `drush cr` cannot run automatically.** This blocks all hook-dependent changes indefinitely until a human runs `drush cr` manually or secrets are restored.

## Prevention
- Before deploying any new hook registration, verify GitHub Actions SSH secrets are set: `HOST`, `USERNAME`, `PRIVATE_KEY` in repo Settings → Secrets → Actions.
- If secrets are absent, escalate to Board (via CEO) immediately — do not proceed with hook-dependent changes expecting automated `drush cr`.
- Alternatively: after committing a new hook, add a manual `drush cr` step to the release checklist and confirm it runs before QA.

## Resolution command (human-only)
```bash
ssh ubuntu@forseti.life
cd /home/ubuntu/forseti.life
git pull --rebase origin main
cd sites/forseti
vendor/bin/drush cr
```

## Verification after drush cr
```bash
curl -s https://forseti.life/ | grep roadmap
# Expected: href="/roadmap" (NOT href="/index.php/roadmap")
curl -sI https://forseti.life/roadmap/PROJ-002
# Expected: HTTP/1.1 200 OK
```

## Related
- Commits: `6f82d6e92` (htaccess), `7b31fb415` (hook_url_outbound_alter)
- Blocked cycles: 13 (20260422 release-m QA gate)
- See also: `org-chart/sites/forseti.life/site.instructions.md` — "Code is always live via symlink" note (does NOT apply to hook registry)
