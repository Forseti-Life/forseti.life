# QA Regression Checklist

This file is a running list of targeted regression checks derived from completed Dev items.

- Automated baseline (always): URL validation + role-based permission checks (see `runbooks/role-based-url-audit.md`).
- Manual/targeted checks: one checklist entry per completed Dev item.

## Checklist

- [x] 20260414-proj-009-phase1-security-gate — NO-GO (dev commit 51091561a; audit at sessions/dev-open-source/artifacts/20260414-proj-009-phase1-security-gate-drupal-ai-conversation.md; current tree: no committed secrets/key files; 4 public-safety blockers in ai_conversation tree: HQ session coupling + stale absolute path in AIApiService.php, thetruthperspective.logging ref in ConfigurableLoggingTrait.php, Forseti-specific install prompt; history scrub required; AWS credential rotation unconfirmed; see qa-infra outbox: sessions/qa-infra/outbox/20260414-unit-test-20260414-proj-009-phase1-security-gate.md)
