#!/usr/bin/env bash
# HQ health heartbeat: checks all orchestration loops and restarts any that are down.
#
# Usage: bash scripts/hq-health-heartbeat.sh
# Exit 0: all loops healthy (or successfully restarted)
# Exit 1: one or more loops could not be restarted (manual intervention required)
#
# Intended cron: */2 * * * * .../scripts/hq-health-heartbeat.sh >> /tmp/hq-health-heartbeat.log 2>&1

set -uo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

HEARTBEAT_LOG="/tmp/hq-health-heartbeat.log"
ALERT_LOG="/tmp/hq-health-alert.log"
ts="$(date -Iseconds)"

log() { printf '[%s] %s\n' "$ts" "$*" | tee -a "$HEARTBEAT_LOG" >/dev/null 2>&1 || true; }
alert() { printf '[%s] WARN %s\n' "$ts" "$*" | tee -a "$ALERT_LOG" | tee -a "$HEARTBEAT_LOG" >/dev/null 2>&1 || true; }

legacy_agent_exec_pids() {
  ps -eo pid=,args= 2>/dev/null | awk '/[s]cripts\/agent-exec-loop\.sh run/ {print $1}'
}

check_and_restart_loop() {
  local name="$1"     # human label
  local script="$2"   # path to loop script (relative to ROOT_DIR)
  local start_args="${3:-start}"

  local result=0
  if "$ROOT_DIR/$script" verify >/dev/null 2>&1; then
    log "ok: ${name}"
    return 0
  fi

  alert "${name} is DOWN — attempting restart"
  "$ROOT_DIR/$script" "$start_args" >/dev/null 2>&1 || true
  sleep 1

  if "$ROOT_DIR/$script" verify >/dev/null 2>&1; then
    log "restarted: ${name}"
    return 0
  fi

  alert "${name} restart FAILED — manual intervention required"
  return 1
}

check_publisher() {
  local pid_file="${ROOT_DIR}/.publish-forseti-agent-tracker-loop.pid"
  if [ ! -f "$pid_file" ]; then
    log "ok: publisher (no persistent loop; runs on-demand)"
    return 0
  fi
  local pid
  pid="$(cat "$pid_file" 2>/dev/null || echo '')"
  if [[ "$pid" =~ ^[0-9]+$ ]] && ps -p "$pid" >/dev/null 2>&1; then
    log "ok: publisher (pid ${pid})"
    return 0
  fi
  alert "publisher loop PID stale (pid=${pid}) — not restarting (on-demand script; will fire on next cron tick)"
  return 0
}

stop_legacy_agent_exec_loop() {
  local found=0
  if "$ROOT_DIR/scripts/agent-exec-loop.sh" verify >/dev/null 2>&1; then
    found=1
  elif [ -n "$(legacy_agent_exec_pids)" ]; then
    found=1
  fi

  if [ "$found" -ne 1 ]; then
    return 0
  fi

  alert "legacy agent-exec-loop is running — stopping it to avoid duplicate agent execution"
  "$ROOT_DIR/scripts/agent-exec-loop.sh" stop >/dev/null 2>&1 || true
  while IFS= read -r pid; do
    [[ "$pid" =~ ^[0-9]+$ ]] || continue
    kill "$pid" >/dev/null 2>&1 || true
    sleep 0.2
    if ps -p "$pid" >/dev/null 2>&1; then
      kill -9 "$pid" >/dev/null 2>&1 || true
    fi
  done < <(legacy_agent_exec_pids)
  sleep 1

  if "$ROOT_DIR/scripts/agent-exec-loop.sh" verify >/dev/null 2>&1 || [ -n "$(legacy_agent_exec_pids)" ]; then
    alert "legacy agent-exec-loop stop FAILED — manual intervention required"
    return 1
  fi

  log "stopped: legacy agent-exec-loop"
  return 0
}

any_failed=0

check_and_restart_loop "orchestrator-loop" "scripts/orchestrator-loop.sh" "start 60" || any_failed=1
stop_legacy_agent_exec_loop || any_failed=1
check_publisher || true

if [ "$any_failed" -eq 0 ]; then
  log "heartbeat: all loops healthy"
  exit 0
else
  alert "heartbeat: one or more loops could not be restarted — see ${ALERT_LOG}"
  exit 1
fi
