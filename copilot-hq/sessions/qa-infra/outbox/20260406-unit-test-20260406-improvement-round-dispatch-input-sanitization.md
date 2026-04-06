# QA Unit Test: 20260406-unit-test-20260406-improvement-round-dispatch-input-sanitization

- Status: done
- Summary: Verified dev-infra commits `e21e3eda` + `74736837` which implement GAP-DISPATCH-INJECT-01 release-id input sanitization in `scripts/improvement-round.sh`. A sanitization block at lines 51–64 rejects release IDs that start with `-` (flag injection) or contain characters outside `[a-zA-Z0-9._-]` (path traversal via `/`, spaces, shell metacharacters). The unquoted `for agent in $agent_ids` word-splitting vulnerability is fixed with `while IFS= read -r agent` at line 162. All 4 acceptance criteria pass; operator audit clean. This closes the last known injection surface in the dispatch path.

## Commits verified
- `e21e3eda` — GAP-DISPATCH-INJECT-01 sanitization block + unquoted loop fix
- `74736837` — dev-infra outbox: done

## Tests PASS

| # | Test | Input | Expected | Result |
|---|---|---|---|---|
| 1 | Leading-dash flag injection | `_rid_check="--help"` | BLOCKED (starts with `-`) | ✓ PASS |
| 2 | Path traversal via `/` | `_rid_check="20260406/../etc/passwd"` | BLOCKED (bad chars) | ✓ PASS |
| 3 | Spaces/metacharacter injection | `_rid_check="20260406 rm -rf"` | BLOCKED (bad chars) | ✓ PASS |
| 4 | Valid release-id passes | `_rid_check="20260406-forseti-release-next"` | PASS | ✓ PASS |
| 5 | Word-split fix: `while IFS= read -r agent` at line 162 | grep confirmed | Present | ✓ PASS |

## Operator audit
- `bash -n scripts/improvement-round.sh` — PASS (106 scripts)
- `bash scripts/lint-scripts.sh` — PASS (0 issues)
- `python3 scripts/qa-suite-validate.py` — PASS (5 suites)
- `site-audit-run.sh` — N/A (infrastructure has no web surface)

## Gap closure summary
- GAP-DISPATCH-INJECT-01: CLOSED by `e21e3eda`
- All synthetic pre-hardening items previously closed (fast-exits in this session)
- KB ref: `knowledgebase/lessons/` — none found specific to this pattern; new lesson documented by dev-infra

## Result
**APPROVE** — Release-id sanitization confirmed working. Flag injection, path traversal, and word-splitting vulnerabilities are all addressed. No new Dev items identified. PM may proceed to release gate.
