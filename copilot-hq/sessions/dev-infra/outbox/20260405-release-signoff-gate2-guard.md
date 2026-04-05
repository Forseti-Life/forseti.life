- Status: done
- Summary: Gate 2 APPROVE evidence guard implemented in `scripts/release-signoff.sh` as part of commit `f8d59f58`. Before writing any PM signoff artifact, the script now scans `sessions/qa-<team>/outbox/` for files containing both the release-id argument and the string `APPROVE`; if none are found it exits 1 with an explicit error message. Stale orchestrator artifacts (containing `Signed by: orchestrator`) are detected and treated as invalid, allowing overwrite after the guard passes. The `qa_agent` field is resolved from `org-chart/products/product-teams.json` (4th tab field), with fallback to `qa-<team_id>`. Verified: `bash scripts/release-signoff.sh dungeoncrawler fake-release-id-test` exits 1 with "Gate 2 APPROVE evidence not found".

## Next actions
- No further action on this item.
- `20260405-hq-script-path-migration` — next in queue

## Blockers
- None.

## Needs from CEO
- N/A.

## ROI estimate
- ROI: 15
- Rationale: Closes the near-miss class where orchestrator-pre-populated signoff artifacts could have allowed a push with unverified code. One guard change eliminates phantom-Gate-2-pass releases permanently.

---

## Verification evidence

```bash
$ bash scripts/release-signoff.sh dungeoncrawler fake-release-id-test
ERROR: Gate 2 APPROVE evidence not found for release 'fake-release-id-test'
  Searched: sessions/qa-dungeoncrawler/outbox/ for files containing both 'fake-release-id-test' and 'APPROVE'
BLOCKED: PM signoff requires Gate 2 QA APPROVE before it can be issued.
Exit code: 1
```

## Implementation summary

Changes to `scripts/release-signoff.sh`:
1. Python lookup extended to output `qa_agent` as 4th tab-delimited field
2. `IFS=$'\t' read -r team_id site pm_agent qa_agent` captures it; fallback: `qa-${team_id}`
3. Gate 2 guard block inserted after team lookup, before early-exit check:
   - `grep -rl "$release_id" sessions/${qa_agent}/outbox/` → `xargs grep -l "APPROVE"`
   - If no match: print error to stderr, exit 1
4. Stale orchestrator artifact detection: `grep -q "Signed by: orchestrator"` on existing `$out_file`
   - If stale: `is_stale_orchestrator=1`, skip early-exit, allow overwrite
5. Real PM signoff check unchanged: if `$out_file` exists AND is not stale orchestrator → exit 0 (already signed)

## Commit
- `f8d59f58` — feat(dev-infra): gate-transition auto-routing + Gate 2 APPROVE guard
