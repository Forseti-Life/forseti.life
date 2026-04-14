# QA Regression Checklist

This file is a running list of targeted regression checks derived from completed Dev items.

- Automated baseline (always): URL validation + role-based permission checks (see `runbooks/role-based-url-audit.md`).
- Manual/targeted checks: one checklist entry per completed Dev item.

## Checklist

- [x] 20260414-proj-009-phase1-security-gate — NO-GO (dev commit 51091561a; audit at sessions/dev-open-source/artifacts/20260414-proj-009-phase1-security-gate-drupal-ai-conversation.md; current tree: no committed secrets/key files; 4 public-safety blockers in ai_conversation tree: HQ session coupling + stale absolute path in AIApiService.php, thetruthperspective.logging ref in ConfigurableLoggingTrait.php, Forseti-specific install prompt; history scrub required; AWS credential rotation unconfirmed; see qa-infra outbox: sessions/qa-infra/outbox/20260414-unit-test-20260414-proj-009-phase1-security-gate.md)
- [x] 20260414-phase1-security-audit-forseti-open-source — FAIL/NO-GO (dev commit 47e6653752eb; audit at sessions/dev-open-source/artifacts/20260414-phase1-security-audit-report.md; 4 hard blockers: (1) RSA key files still in current tree at sites/forseti/keys/; (2) confirmed AWS credentials in git history (74da62ed4, e9f005f93); (3) sessions/+prod-config/+database-exports/ still in history; (4) export-public-mirror.sh recreates inbox/responses/.gitkeep; .env.example: PASS; 9-script audit: 1 issue (bedrock-assist.sh host paths); no raw-history mirror safe; see qa-infra outbox: sessions/qa-infra/outbox/20260414-unit-test-20260414-phase1-security-audit-forseti-open-source.md)
- [x] 20260414-fix-from-qa-block-infrastructure — APPROVE (dev commit f360335d8 verified; all 4 QA blockers resolved: HQ session coupling + stale abs path removed from AIApiService.php (file deleted/replaced, 159 lines removed), ConfigurableLoggingTrait.php now reads ai_conversation.settings (not thetruthperspective.logging), ai_conversation.settings.yml install prompt now neutral public default; blocker grep clean; PHP lint PASS on both files; remaining Phase 1 gates (key files, history scrub, AWS rotation) unchanged — outside this fix scope; see qa-infra outbox: sessions/qa-infra/outbox/20260414-unit-test-20260414-fix-from-qa-block-infrastructure.md)
