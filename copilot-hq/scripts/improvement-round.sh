#!/usr/bin/env bash
set -euo pipefail

# Create post-release process review inbox items for PM + CEO seats.
# Designed to be consumed by agent-exec-loop.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

DATE_YYYYMMDD="${1:-$(date +%Y%m%d)}"
TOPIC="${2:-improvement-round}"

# Gate: if TOPIC encodes a specific release-id (improvement-round-YYYYMMDD-*),
# confirm both PM signoffs are present AND none are stale orchestrator artifacts
# before queuing any inbox items.
# Pattern: improvement-round-<YYYYMMDD>-<anything>  → release-id = <YYYYMMDD>-<anything>
# GAP-26B-02: premature dispatch caused wasted fast-exit cycles when improvement-round
# was dispatched before Gate 2 ran or while only orchestrator-pre-populated signoffs existed.
if [[ "$TOPIC" =~ ^improvement-round-([0-9]{8}-.+)$ ]]; then
  release_id="${BASH_REMATCH[1]}"
  slug="$(printf '%s' "$release_id" | tr -cs 'A-Za-z0-9._-' '-' | sed 's/^-//;s/-$//' | cut -c1-80)"

  # Step 1: require all coordinated PM signoffs to be present.
  if ! bash scripts/release-signoff-status.sh "$release_id" >/dev/null 2>&1; then
    echo "SKIP: release '${release_id}' not fully signed off; improvement-round not queued. Try again after shipment."
    exit 0
  fi

  # Step 2: reject stale orchestrator-generated signoff artifacts.
  # A signoff containing "Signed by: orchestrator" is a pre-population artifact,
  # not a real PM signoff — do not treat it as release confirmation.
  stale_found=0
  while IFS= read -r signoff_file; do
    if [ -f "$signoff_file" ] && grep -q "Signed by: orchestrator" "$signoff_file" 2>/dev/null; then
      echo "SKIP: stale orchestrator signoff artifact detected: ${signoff_file}; improvement-round not queued."
      stale_found=1
      break
    fi
  done < <(find sessions -type f -path "*/artifacts/release-signoffs/${slug}.md" 2>/dev/null)

  if [ "$stale_found" -eq 1 ]; then
    exit 0
  fi

  echo "OK: release '${release_id}' confirmed signed off by real PM(s); proceeding with improvement-round dispatch."
fi

# Only create review items for configured PM + CEO seats (org-chart), not every
# directory under sessions/ (which may include archived/escalation thread ids).
agent_ids="$(
  python3 - <<'PY'
import yaml
from pathlib import Path
p = Path('org-chart/agents/agents.yaml')
if not p.exists():
    raise SystemExit(0)
data = yaml.safe_load(p.read_text(encoding='utf-8', errors='ignore'))
for agent in data.get('agents', []):
    if not agent.get('paused', False):
        print(agent['id'])
PY
)"
for agent in $agent_ids; do
  inbox_dir="sessions/${agent}/inbox/${DATE_YYYYMMDD}-${TOPIC}"

  # Don't duplicate.
  if [ -d "$inbox_dir" ]; then
    continue
  fi

  mkdir -p "$inbox_dir"
  printf '3\n' > "$inbox_dir/roi.txt"

  cat > "$inbox_dir/command.md" <<'MD'
- command: |
    Post-release process and gap review (PM/CEO):
    1) Review the just-finished release execution and identify the top 1-3 process gaps that caused delay, rework, or ambiguous ownership.
    2) For each gap, define one concrete follow-through action item with owner, acceptance criteria, and ROI.
    3) Queue required follow-through inbox item(s) for the owning seat in the same cycle where feasible.

    Output must follow the required outbox template and include SMART outcomes for proposed process fixes.
MD

done

echo "Created PM+CEO post-release review inbox items for ${DATE_YYYYMMDD}-${TOPIC}" 
