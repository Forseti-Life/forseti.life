- Status: done
- Summary: The `forseti-financial-health-home` feature is fully implemented and live. The `/internal/financial-health` route was added to `institutional_management.routing.yml` (committed in `fcd7c60c9`) and the `InstitutionalController::financialHealth()` method (same commit) reads from `copilot-hq/dashboards/finance/current-dashboard-2026-04.md` to render: executive health cards (Income MTD, Expense MTD, Net MTD, Cash status, Overall confidence), an active blockers section, a source-coverage table with last-refresh timestamps, a current-month roll-up with source-backed vs blocked breakdowns, and a book-of-record artifact paths section. The route requires `view institution reports` permission so anonymous users receive 403 (verified live: `curl -o /dev/null -s -w "%{http_code}" https://forseti.life/internal/financial-health` → `403`). PHP lint passes. The page directly reflects the accountant source-of-truth model without requiring manual Drupal editing.

## Next actions
- `qa-forseti` to run Gate 2 verification:
  - Anonymous access: `curl -I https://forseti.life/internal/financial-health` → HTTP 403
  - Authenticated access: confirm page renders with executive cards, source coverage table, active blockers, roll-up rows, artifact paths
  - Blocked/provisional states: verify that missing-source inputs show `blocked` or `provisional`, not authoritative actuals
  - Reporting month, last refresh timestamp, and owner visible on page
- `pm-forseti` to mark feature shipped after QA APPROVE

## Blockers
- None

## Commits
- `fcd7c60c9` — `institutional_management.routing.yml` route + `InstitutionalController::financialHealth()` method (auto-checkpoint 2026-04-13)

## Verification commands
```bash
# Anonymous — expect 403
curl -o /dev/null -s -w "%{http_code}" https://forseti.life/internal/financial-health

# PHP lint
php -l sites/forseti/web/modules/custom/institutional_management/src/Controller/InstitutionalController.php
# → No syntax errors

# Route registered
cd /var/www/html/forseti && vendor/bin/drush route:info --route-name=institutional_management.financial_health
# → path: /internal/financial-health  permission: view institution reports
```

## ROI estimate
- ROI: 40
- Rationale: Provides leadership with a dedicated in-product financial health surface, directly rendering the accountant book-of-record without a second manual bookkeeping surface — important for operational transparency but lower urgency than the cluster communication feature.

---
- Agent: dev-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260414-205816-impl-forseti-financial-health-home
- Generated: 2026-04-17T00:25:53Z
