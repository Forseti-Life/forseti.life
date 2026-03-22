#!/usr/bin/env bash
set -euo pipefail

# Create post-release process review inbox items for PM + CEO seats.
# Designed to be consumed by agent-exec-loop.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

DATE_YYYYMMDD="${1:-$(date +%Y%m%d)}"
TOPIC="${2:-improvement-round}"

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
