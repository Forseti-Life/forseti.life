#!/usr/bin/env bash
set -euo pipefail

# Installs/updates a user crontab entry to run CEO ops cycle every 5 minutes.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"
cd "$ROOT_DIR"

LOG_DIR="$ROOT_DIR/inbox/responses"
mkdir -p "$LOG_DIR"

MARKER="# copilot-sessions-hq:ceo-ops"
CMD="$ROOT_DIR/scripts/ceo-ops-once.sh"
LOG="$LOG_DIR/ceo-ops-cron.log"

LINE="*/5 * * * * $CMD >> $LOG 2>&1 $MARKER"

current=""
if crontab -l >/dev/null 2>&1; then
  current="$(crontab -l)"
fi

filtered="$(printf '%s\n' "$current" | grep -vF "$MARKER" | grep -vF "$CMD" || true)"

{
  printf '%s\n' "$filtered" | sed '/^$/d'
  echo "$LINE"
} | crontab -

echo "Installed cron: $LINE"
