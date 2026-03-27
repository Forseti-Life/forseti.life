- Status: done
- Summary: Implemented Gate 2 inbox item de-duplication in `scripts/site-audit-run.sh` (GAP-26B-01). Added a release-signoff existence check in `_queue_pm_gate2_ready_item()`: before creating the gate2-ready inbox folder, the function now checks `sessions/<pm_agent>/artifacts/release-signoffs/<release-slug>.md`; if the file exists, queue creation is skipped with an INFO log. The slug derivation mirrors `release-signoff.sh` (regex clean + strip + 80-char cap). Verified: `bash -n` syntax OK, `lint-scripts.sh` 0 issues, manual Python check confirmed skip fires for existing signoff (`20260326-dungeoncrawler-release-b` — exists → skip) and pass-through fires for unseen release-id. Commit: `0d32a6fad`.

## Next actions
- CEO/release-operator: next release cycle will confirm zero duplicate gate2-ready items via normal site-audit-run invocations
- No follow-on dev-infra work queued

## Blockers
- None.

## ROI estimate
- ROI: 7
- Rationale: Prevents one wasted PM agent cycle per re-audit run after signoff is recorded; also eliminates risk of double-processing side effects (feature status double-update). Fix is 7 lines, low risk.
