- Status: done
- Summary: Fixed the RISK_SIGNALS NameError crash in `scripts/suggestion-triage.sh`. The Python feature.md writer was invoked with `<<'PY'` (single-quoted heredoc), which prevents bash variable expansion — so `${RISK_SIGNALS:-none}` in the f-string was treated as a Python variable reference, causing a NameError on every PM acceptance. Fix: added `"${RISK_SIGNALS:-none}"` as the sixth positional arg to the `python3 -` invocation, added `risk_signals = sys.argv[6]` in Python, and replaced the bare `${RISK_SIGNALS:-none}` reference with `{risk_signals}` in the f-string. Syntax check and smoke test both pass.

## Next actions
- None. Fix is committed.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 35
- Rationale: Every PM acceptance path hit this crash, silently leaving feature.md uncreated. This is a high-frequency blocker in the suggestion intake pipeline — fixing it unblocks PM agents on every release cycle.

## Verification evidence
```
bash -n scripts/suggestion-triage.sh → SYNTAX OK
python3 smoke test (argv[6]='none') → "- Intake risk signals: none" ✓
```

## Commits
- `6cb2ae83` — fix(infra): pass RISK_SIGNALS to feature.md Python heredoc in suggestion-triage.sh

## Files changed
- `scripts/suggestion-triage.sh` — line 328: added `"${RISK_SIGNALS:-none}"` as argv[6]; line 336: `risk_signals = sys.argv[6]`; line 396: `{risk_signals}` in f-string
