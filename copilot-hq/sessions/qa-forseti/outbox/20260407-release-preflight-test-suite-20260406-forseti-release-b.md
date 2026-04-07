# QA Release Preflight: 20260406-forseti-release-b

- Status: done
- Summary: Release-b preflight complete for forseti.life. Reviewed all 5 QA scripts (`site-audit-run.sh`, `site-full-audit.py`, `site-validate-urls.py`, `drupal-custom-routes-audit.py`, `role-permissions-validate.py`) and `qa-permissions.json`. No stale paths, no `keithaumiller` references, no stale `drupal_root` values. Production audit gate (`ALLOW_PROD_QA=1`) correctly enforced in `site-audit-run.sh`. `qa-permissions.json` current: 4 active roles (anon, authenticated, content_editor, administrator), 23 rules, `drupal_root=/var/www/html/forseti`, `drush_qa_command_prefix=jhtr`. Placeholder roles `firefighter`, `fire_dept_admin`, `nfr_researcher`, `nfr_administrator` confirmed 0 permissions (verified via `drush role:list`). `qa-suites/products/forseti/suite.json` valid JSON, 16 suites. No changes needed.

## Checks performed

| Target | Result |
|---|---|
| `scripts/site-audit-run.sh` | Current — no stale refs; ALLOW_PROD_QA gate intact |
| `scripts/site-full-audit.py` | Current — no stale refs |
| `scripts/site-validate-urls.py` | Current — no stale refs |
| `scripts/drupal-custom-routes-audit.py` | Current — no stale refs |
| `scripts/role-permissions-validate.py` | Current — no stale refs |
| `qa-permissions.json` | Current — 4 roles, 23 rules, correct drupal_root |
| Placeholder roles (firefighter, fire_dept_admin, nfr_researcher, nfr_administrator) | Confirmed 0 permissions — NOT in qa-permissions.json (correct) |
| `qa-suites/products/forseti/suite.json` | Valid JSON, 16 suites |

## Next actions
- Proceed with release-b QA verification items as dispatched.
- Persistent infra note (carry-forward): `vendor/bin/phpunit` absent at `sites/forseti/vendor/` — 4 PHPUnit suites remain static-only until `composer install` is run (pm-forseti owns escalation to pm-infra).

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 30
- Rationale: Preflight confirms QA toolchain is current for release-b; no rework required this cycle. Unblocks all subsequent release-b verification items.
