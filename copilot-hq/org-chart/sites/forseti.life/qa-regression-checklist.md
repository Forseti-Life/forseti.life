# QA Regression Checklist

This file is a running list of targeted regression checks derived from completed Dev items.

- Automated baseline (always): URL validation + role-based permission checks (see `runbooks/role-based-url-audit.md`).
- Manual/targeted checks: one checklist entry per completed Dev item.

## Checklist

- [x] 20260224-improvement-round — BATCH CLOSED: dev outbox Status: done; content-only seat instructions update; no product code changed.
- [x] 20260225-daily-review — BATCH CLOSED: dev outbox Status: done; content-only (feedback artifact + queue noise analysis); no product code changed.
- [x] 20260225-205547-qa-findings-forseti.life-23-retry-1772074430 — BATCH CLOSED: dual-label queue noise (retry variant); underlying violation content_editor/talk-with-forseti_content 403 fixed by CEO drush commit 3dc9287 (use ai conversation granted, confirmed 2026-02-27).
- [x] 20260225-improvement-round — BATCH CLOSED: dev outbox Status: done; content-only seat instructions update; no product code changed.
- [x] 20260225-222352-qa-findings-forseti.life-8 — BATCH CLOSED: dual-label queue noise (forseti.life variant); underlying violation fixed by CEO drush commit 3dc9287.
- [x] 20260226-060157-qa-findings-forseti.life-1 — BATCH CLOSED: dual-label queue noise (forseti.life variant); underlying violation fixed by CEO drush commit 3dc9287.
- [x] 20260226-062812-qa-findings-forseti.life-2 — BATCH CLOSED: dual-label queue noise (forseti.life variant); underlying violation fixed by CEO drush commit 3dc9287.
- [x] 20260226-070046-qa-findings-forseti.life-1 — BATCH CLOSED: confirmed dual-label duplicate by dev-forseti outbox ("eighth confirmed duplicate pair"); underlying violation fixed by CEO drush commit 3dc9287.
- [x] 20260226-improvement-round-20260226-dungeoncrawler-release — VERIFIED 2026-02-27 APPROVE; content-only seat instructions update (dual-label + probe guidance); no regression; audit run 20260226-194054 clean (1 pre-existing false negative). See: sessions/qa-forseti/artifacts/20260226-unit-test-improvement-round-dungeoncrawler-release/verification-report.md
- [x] 20260226-improvement-round-20260226-forseti-release — BATCH CLOSED: dev outbox Status: done; content-only (config-file permission verification pattern, commit 6b2c292); no product code changed.
- [x] 20260227-073920-release-b-implementation — VERIFIED 2026-02-27 BLOCK; forseti-jobhunter-profile HTTP/ACL PASS; forseti-jobhunter-e2e-flow BLOCK (submission.success=false, DEF-001: empty data-csrf-token on btn-save-job); role-based URL audit PASS (0 violations). See: sessions/qa-forseti/artifacts/20260227-unit-test-release-b-implementation/verification-report.md
- [x] 20260227-improvement-round-20260226-dungeoncrawler-release-c — BATCH CLOSED: dev outbox Status: done; content-only (Gate 1 grep cheat sheet, commit a3810f8); no product code changed.
- [x] 20260228-084923-qa-findings-forseti-life-44 — BATCH CLOSED: dev outbox Status: done; 43/44 ACL violations fixed (drush role:perm:add authenticated 'access job hunter', routing.yml anon-access fix commit d015207f7); latest continuous audit 20260228-115225 clean (0 violations, 0 failures). 1 open `user-register` ACL item escalated to PM for product decision.
- [x] 20260228-improvement-round-20260228-dungeoncrawler-release — BATCH CLOSED: dev outbox Status: done; content-only (config drift pre-flight proposal, KB entry commit 875fa087); no product code changed.
- [x] 20260228-fix-500-jobhunter-credentials-and-companyresearch — BATCH CLOSED: dev outbox Status: done; no code changes required (failures already resolved by earlier permission grant); latest audit 20260228-115225 clean.
- [ ] 20260319-improvement-round-20260315-dungeoncrawler-release-b — targeted regression check (see dev outbox: sessions/dev-forseti/outbox/20260319-improvement-round-20260315-dungeoncrawler-release-b.md)
- [ ] 20260322-improvement-round-20260322-dungeoncrawler-release-next — targeted regression check (see dev outbox: sessions/dev-forseti/outbox/20260322-improvement-round-20260322-dungeoncrawler-release-next.md)
- [ ] 20260322-recover-impl-copilot-agent-tracker — targeted regression check (see dev outbox: sessions/dev-forseti-agent-tracker/outbox/20260322-recover-impl-copilot-agent-tracker.md)
- [ ] 20260322-improvement-round-20260322-dungeoncrawler-release-b — targeted regression check (see dev outbox: sessions/dev-forseti/outbox/20260322-improvement-round-20260322-dungeoncrawler-release-b.md)
- [ ] 20260322-improvement-round-20260322-forseti-release-next — targeted regression check (see dev outbox: sessions/dev-forseti-agent-tracker/outbox/20260322-improvement-round-20260322-forseti-release-next.md)
- [ ] 20260323-improvement-round-20260322-dungeoncrawler-release-b — targeted regression check (see dev outbox: sessions/dev-forseti/outbox/20260323-improvement-round-20260322-dungeoncrawler-release-b.md)
- [ ] 20260326-improvement-round-20260322-dungeoncrawler-release-b — targeted regression check (see dev outbox: sessions/dev-forseti/outbox/20260326-improvement-round-20260322-dungeoncrawler-release-b.md)
- [ ] 20260322-192833-qa-findings-forseti-life-1 — targeted regression check (see dev outbox: sessions/dev-forseti/outbox/20260322-192833-qa-findings-forseti-life-1.md)
- [ ] 20260326-improvement-round-20260326-dungeoncrawler-release-b — targeted regression check (see dev outbox: sessions/dev-forseti/outbox/20260326-improvement-round-20260326-dungeoncrawler-release-b.md)
- [ ] 20260322-improvement-round-20260322-forseti-release-b — targeted regression check (see dev outbox: sessions/dev-forseti/outbox/20260322-improvement-round-20260322-forseti-release-b.md)
- [ ] 20260327-improvement-round-20260326-dungeoncrawler-release-b — targeted regression check (see dev outbox: sessions/dev-forseti/outbox/20260327-improvement-round-20260326-dungeoncrawler-release-b.md)
- [ ] 20260327-improvement-round-20260327-dungeoncrawler-release-b — targeted regression check (see dev outbox: sessions/dev-forseti/outbox/20260327-improvement-round-20260327-dungeoncrawler-release-b.md)
