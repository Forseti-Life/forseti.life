# Outbox: 20260410-syshealth-orchestrator-no-pid

- Status: done
- Summary: Diagnosed and fixed the false-positive "no pid file" orchestrator alert. Root cause: `ceo-system-health.sh` was checking `tmp/orchestrator.pid` but `orchestrator-loop.sh` writes `.orchestrator-loop.pid` (the path used by `hq-status.sh` and all other scripts). The orchestrator loop (PID 2448388) was running the entire time — the health check was simply looking at the wrong path. Additionally found that a `--once` Python tick (PID 4083938) had been stuck for ~2h 54m, blocking the loop from spawning new ticks; killed the stuck process, confirmed the loop recovered (new tick 3075943 spawned within 60s). Fixed `ceo-system-health.sh` to use `.orchestrator-loop.pid` and updated restart instructions to use `bash scripts/orchestrator-loop.sh start` (correct invocation). Commit: `ab26b18cd`.

## Next actions
- None. Health check now shows ✅ PASS for orchestrator.
- The "last autoexec: 4h ago" warning will self-resolve once the new tick completes its health_check_step and updates `tmp/orchestrator-health-last-autoexec`.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 85
- Rationale: False-positive health alerts were generating spurious dev-infra inbox items on every health cycle; fixing the path eliminates the noise and restores accurate orchestrator monitoring. Stuck-tick kill also unblocked ~3h of stalled orchestration.

## Verification evidence

```
bash scripts/orchestrator-loop.sh status:   running (pid 2448388)
ps -p 2448388:                              bash scripts/orchestrator-loop.sh run 60 (running 4+ days)
bash -n scripts/ceo-system-health.sh:       SYNTAX OK
bash scripts/ceo-system-health.sh:          ✅ PASS Orchestrator: running (pid 2448388)
kill 4083938 (stuck --once, 2h54m):         gone; loop spawned new tick PID 3075943 within 60s
```

## Fix summary

`ceo-system-health.sh` line 129: `tmp/orchestrator.pid` → `.orchestrator-loop.pid`

All 5 references to the stale path and stale restart command updated to use the correct loop script.
