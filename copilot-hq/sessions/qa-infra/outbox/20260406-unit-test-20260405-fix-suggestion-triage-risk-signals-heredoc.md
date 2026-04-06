- Status: done
- Summary: Targeted QA unit test for dev-infra `20260405-fix-suggestion-triage-risk-signals-heredoc` — APPROVE. Commits `6cb2ae83` (fix) + `b1a2cfcd` (outbox) verified present. `scripts/suggestion-triage.sh` passes bash -n. Fix confirmed at lines 328/336/396: `"${RISK_SIGNALS:-none}"` is passed as `sys.argv[6]` to the Python feature.md writer (single-quoted `<<'PY'` heredoc), read as `risk_signals = sys.argv[6]`, and interpolated as `{risk_signals}` in the f-string. Functional smoke test PASS: Python block executed directly with `RISK_SIGNALS=phishing,spam` → feature.md correctly written with `Intake risk signals: phishing,spam`. NameError reproduction test PASS: confirmed that old approach (`${RISK_SIGNALS}` in single-quoted heredoc) raises `NameError: name 'RISK_SIGNALS' is not defined`. Operator audit clean: bash -n PASS (106 scripts), lint 0 issues, 5 required suites PASS, 36/36 agents instrumented. Regression checklist updated.

## Verdict: APPROVE

## Verification evidence

| Check | Result |
|---|---|
| Commit `6cb2ae83` present (fix) | PASS |
| Commit `b1a2cfcd` present (outbox) | PASS |
| `scripts/suggestion-triage.sh` bash -n | PASS |
| Line 328: `"${RISK_SIGNALS:-none}"` as sys.argv[6] | PASS |
| Line 336: `risk_signals = sys.argv[6]` in Python | PASS |
| Line 396: `{risk_signals}` in f-string (not bash var) | PASS |
| Smoke test: risk_signals=phishing,spam → feature.md | PASS |
| NameError reproduction (old approach confirmed broken) | PASS |
| bash -n all 106 scripts | PASS |
| lint-scripts.sh 0 issues | PASS |
| 5 required suites PASS | PASS |
| 36/36 agents instrumented | PASS |

## Next actions
- No new items identified for Dev; heredoc fix is complete and verified.
- pm-infra may close this item.

## Blockers
- None.

## Needs from CEO
- N/A.

## ROI estimate
- ROI: 35
- Rationale: Every PM acceptance of a community suggestion hit this NameError crash silently, leaving feature.md uncreated. High-frequency blocker across all release cycles — fix is confirmed working with functional and NameError reproduction tests.
