- Status: done
- Summary: Implemented full gate-transition auto-routing in `scripts/route-gate-transitions.sh` (new, 215 lines) and hooked it into `scripts/agent-exec-loop.sh` after each non-CEO agent execution. All 3 routing patterns are live: QA BLOCK → dev fix inbox item, Gate 2 APPROVE → PM signoff inbox item, and PM non-forseti signoff → pm-forseti coordinated signoff inbox item. Separately completed the Gate 2 APPROVE guard in `scripts/release-signoff.sh`: signoff is blocked with exit 1 unless QA outbox evidence exists for the release-id, and stale orchestrator-generated artifacts are detected and overwritten. All patterns verified by simulation; KB lesson updated with "Fix applied" note. Committed as `f8d59f58`.

## Next actions
- `20260405-hq-script-path-migration` — audit `/home/keithaumiller` paths in scripts, create `scripts/lib/site-paths.sh` (remaining stale-path gap from improvement round)
- `20260405-cron-resilience-self-healing` — create `scripts/hq-health-heartbeat.sh`, idempotent cron install, `runbooks/cron-recovery.md`
- `20260405-improvement-round-sequencing-fix` — pending review

## Blockers
- None.

## Needs from CEO
- N/A.

## ROI estimate
- ROI: 18
- Rationale: 5 consecutive gate-transition misses documented; each added 24h+ release stall requiring manual CEO intervention. This fix eliminates the #1 structural cause of release pipeline stagnation. Gate 2 guard closes a near-miss risk where a stale orchestrator artifact could have allowed a push with unverified code.

---

## Implementation detail

### scripts/route-gate-transitions.sh (new)
- Called after each `agent-exec-next.sh` invocation in the non-CEO agent loop
- Looks up team from `org-chart/products/product-teams.json` — no hardcoded agent IDs
- Pattern 1 (QA BLOCK → dev fix): detects `Status: done` + `BLOCK` in QA outbox → creates `sessions/<dev_agent>/inbox/YYYYMMDD-fix-from-qa-block-<team>/`; ROI extracted from QA outbox (default 10); Next actions section forwarded
- Pattern 2 (Gate 2 APPROVE → PM signoff): detects `Status: done` + `APPROVE` → creates `sessions/<pm_agent>/inbox/YYYYMMDD-release-signoff-<release-id>/` with ROI 50
- Pattern 3 (PM non-forseti → pm-forseti coordinated signoff): detects `SIGNED_OFF` in PM outbox → reads latest signoff artifact for release-id → creates `sessions/pm-forseti/inbox/YYYYMMDD-coordinated-signoff-<slug>/` with ROI 200
- Idempotent: skips if target inbox dir or outbox file already exists
- Non-blocking: all failures log to stderr and return 0

### scripts/release-signoff.sh (modified)
- `qa_agent` field captured from product-teams.json (4th tab-delimited field); falls back to `qa-<team_id>` if not configured
- Gate 2 guard scans `sessions/${qa_agent}/outbox/` for files containing both `$release_id` and `APPROVE`; exits 1 if none found
- Stale orchestrator artifact check: if existing `$out_file` contains `Signed by: orchestrator`, treat as invalid and allow overwrite after guard passes
- Verified: `bash scripts/release-signoff.sh dungeoncrawler fake-release-id-test` → exit 1

### scripts/agent-exec-loop.sh (modified)
- After each successful non-CEO `agent-exec-next.sh` call (rc=0), extracts processed item name from output and calls `./scripts/route-gate-transitions.sh "$agent" "$item_name"`
- Routing call is `|| true` — never aborts the exec loop

### Simulation commands (documented)
```bash
# Pattern 1: QA BLOCK → Dev fix
echo "- Status: done\nBLOCK\n- Release id: test-release\n- ROI: 25\n## Next actions\n- Fix test_foo" \
  > sessions/qa-dungeoncrawler/outbox/sim-block.md
bash scripts/route-gate-transitions.sh qa-dungeoncrawler sim-block
# → creates sessions/dev-dungeoncrawler/inbox/YYYYMMDD-fix-from-qa-block-dungeoncrawler/

# Pattern 2: Gate 2 APPROVE → PM signoff
echo "- Status: done\nAPPROVE\n- Release id: test-release-b" \
  > sessions/qa-dungeoncrawler/outbox/sim-approve.md
bash scripts/route-gate-transitions.sh qa-dungeoncrawler sim-approve
# → creates sessions/pm-dungeoncrawler/inbox/YYYYMMDD-release-signoff-test-release-b/

# Pattern 3: PM-DC signoff → pm-forseti coordinated signoff
echo "- Status: done\nSIGNED_OFF test-release-b" > sessions/pm-dungeoncrawler/outbox/sim-signoff.md
# (requires signoff artifact at sessions/pm-dungeoncrawler/artifacts/release-signoffs/)
bash scripts/route-gate-transitions.sh pm-dungeoncrawler sim-signoff
# → creates sessions/pm-forseti/inbox/YYYYMMDD-coordinated-signoff-test-release-b/
```

## Commit
- `f8d59f58` — feat(dev-infra): gate-transition auto-routing + Gate 2 APPROVE guard
