#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"
cd "$ROOT_DIR"

LOG_DIR="$ROOT_DIR/inbox/responses"
mkdir -p "$LOG_DIR"

MARKER="# copilot-sessions-hq:agent-exec"
START_CMD="$ROOT_DIR/scripts/agent-exec-loop.sh start 60"
WATCHDOG_CMD="$ROOT_DIR/scripts/agent-exec-watchdog.sh"
LOG="$LOG_DIR/agent-exec-cron.log"

START_LINE="@reboot $START_CMD >> $LOG 2>&1 $MARKER"
WATCHDOG_LINE="*/5 * * * * $WATCHDOG_CMD >> $LOG 2>&1 $MARKER"

current=""
if crontab -l >/dev/null 2>&1; then
  current="$(crontab -l)"
fi

filtered="$(printf '%s\n' "$current" | grep -vF "$MARKER" | grep -vF "$ROOT_DIR/scripts/agent-exec-loop.sh" || true)"

# Back-compat: remove any prior agent-exec-once cron as well.
filtered="$(printf '%s\n' "$filtered" | grep -vF "$ROOT_DIR/scripts/agent-exec-once.sh" || true)"

{
  printf '%s\n' "$filtered" | sed '/^$/d'
  echo "$START_LINE"
  echo "$WATCHDOG_LINE"
} | crontab -

echo "Installed cron: $START_LINE"
echo "Installed cron: $WATCHDOG_LINE"
echo "Cron log: $LOG"
