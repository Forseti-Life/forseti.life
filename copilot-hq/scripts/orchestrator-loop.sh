#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"
cd "$ROOT_DIR"

PIDFILE=".orchestrator-loop.pid"
LOGDIR="inbox/responses"
LATEST="$LOGDIR/orchestrator-latest.log"
mkdir -p "$LOGDIR"

cmd="${1:-start}"
interval="${2:-60}"

read_pid() {
  [ -f "$PIDFILE" ] || { echo ""; return; }
  pid="$(cat "$PIDFILE" 2>/dev/null || true)"
  [[ "$pid" =~ ^[0-9]+$ ]] && echo "$pid" || echo ""
}

is_running() {
  pid="$(read_pid)"
  [ -n "$pid" ] && ps -p "$pid" >/dev/null 2>&1
}

run_orchestrator_once() {
  if [ "$(./scripts/is-org-enabled.sh 2>/dev/null || echo false)" != "true" ]; then
    echo "org disabled; skipping orchestrator run"
    return 0
  fi
  local python_bin="python3"
  [ -x "orchestrator/.venv/bin/python" ] && python_bin="orchestrator/.venv/bin/python"

  "$python_bin" orchestrator/run.py --once \
    --agent-cap "${ORCHESTRATOR_AGENT_CAP:-6}" \
    ${ORCHESTRATOR_NO_PUBLISH:+--no-publish} \
    --kpi-interval "${ORCHESTRATOR_KPI_INTERVAL:-300}" \
    --log-file "$LATEST"
}

case "$cmd" in
  start)
    if is_running; then
      echo "Already running (pid $(read_pid))"
      exit 0
    fi
    setsid bash -c "'$0' run '$interval'" >/dev/null 2>&1 &
    pid=$!
    echo "$pid" > "$PIDFILE"
    echo "Started (pid $pid)"
    ;;

  status)
    if is_running; then
      echo "running (pid $(read_pid))"
    else
      echo "not running"
    fi
    ;;

  verify)
    if is_running; then
      echo "ok (running pid $(read_pid))"
      exit 0
    fi
    echo "ERROR: orchestrator loop not running" >&2
    exit 1
    ;;

  stop)
    pid="$(read_pid)"
    if [ -n "$pid" ] && ps -p "$pid" >/dev/null 2>&1; then
      kill "$pid" >/dev/null 2>&1 || true
      sleep 0.2
      if ps -p "$pid" >/dev/null 2>&1; then
        kill -9 "$pid" >/dev/null 2>&1 || true
      fi
      echo "Stopped (pid $pid)"
      exit 0
    fi
    echo "Not running"
    ;;

  run)
    echo $$ > "$PIDFILE"
    while true; do
      ts="$(date -Iseconds)"
      daylog="$LOGDIR/orchestrator-$(date +%Y%m%d).log"
      out="$(run_orchestrator_once 2>&1 || true)"
      out_line="$(printf '%s' "$out" | tr '\n' ' ' | sed -E 's/[[:space:]]+/ /g; s/[[:space:]]+$//')"
      echo "[$ts] $out_line" | tee -a "$daylog" > "$LATEST"
      sleep "$interval"
    done
    ;;

  *)
    echo "Usage: $0 start|stop|status|verify|run [interval_seconds]" >&2
    exit 1
    ;;
esac
