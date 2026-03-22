#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

# shellcheck source=lib/agents.sh
source "./scripts/lib/agents.sh"

mode="${1:-list}"

count=0

while IFS= read -r agent; do
  if is_paused "$agent"; then
    continue
  fi
  shopt -s nullglob
  out_files=("sessions/${agent}/outbox"/*.md)
  shopt -u nullglob
  if [ "${#out_files[@]}" -eq 0 ]; then
    continue
  fi

  # Only consider the latest outbox file for "currently blocked".
  latest="$(ls -t "sessions/${agent}/outbox"/*.md 2>/dev/null | head -n 1 || true)"
  [ -n "$latest" ] || continue

  status_line="$(grep -im1 '^\- Status:' "$latest" 2>/dev/null || true)"
  status="$(echo "$status_line" | sed 's/^- Status: *//I' | tr '[:upper:]' '[:lower:]' | tr -d '\r')"

  if [ "$status" != "blocked" ] && [ "$status" != "needs-info" ]; then
    continue
  fi

  count=$((count+1))

  if [ "$mode" = "count" ]; then
    continue
  fi

  echo "- ${agent}: $(basename "$latest") [status=${status}]"
  blockers="$(awk 'BEGIN{p=0}
    /^## Blockers/{p=1;next}
    /^## /{p=0}
    {if(p) print}
  ' "$latest" | sed -n '1,20p' | sed 's/^/    /')"
  needs="$(awk 'BEGIN{p=0}
    /^## Needs from CEO/{p=1;next}
    /^## /{p=0}
    {if(p) print}
  ' "$latest" | sed -n '1,20p' | sed 's/^/    /')"

  if [ -n "$blockers" ]; then
    echo "  Blockers:"
    echo "$blockers"
  fi
  if [ -n "$needs" ]; then
    echo "  Needs from CEO:"
    echo "$needs"
  fi

done < <(configured_agent_ids)

if [ "$mode" = "count" ]; then
  echo "$count"
fi
