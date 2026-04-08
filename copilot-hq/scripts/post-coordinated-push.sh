#!/usr/bin/env bash
# post-coordinated-push.sh — Auto-advance each team's release cycle after a coordinated push.
#
# Usage: bash scripts/post-coordinated-push.sh
#
# Run this immediately after the coordinated git push completes (Gate 4).
# It records a team-scoped release signoff for every coordinated team that has an
# active release in tmp/release-cycle-active/ but has not yet been signed off.
# This advances each team's orchestrator release cycle so the next cycle can begin.
#
# Idempotent — safe to re-run.

set -euo pipefail
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

TEAMS_JSON="${ROOT_DIR}/org-chart/products/product-teams.json"
RUNTIME_DIR="${ROOT_DIR}/tmp/release-cycle-active"

echo "=== post-coordinated-push: advancing team release cycles ==="

python3 - "$TEAMS_JSON" "$RUNTIME_DIR" "$ROOT_DIR" <<'PY'
import json, re, sys, subprocess
from datetime import datetime, timezone
from pathlib import Path

teams_json, runtime_dir, root = Path(sys.argv[1]), Path(sys.argv[2]), Path(sys.argv[3])
with open(teams_json) as fh:
    data = json.load(fh)

coord_teams = [t for t in data.get('teams', []) if t.get('active') and t.get('coordinated_release_default')]

team_release_ids = {}

# Step 1 — file any missing team-scoped signoffs
for team in sorted(coord_teams, key=lambda t: t['id']):
    team_id   = team['id']
    pm_agent  = team['pm_agent']
    rid_file  = runtime_dir / f"{team_id}.release_id"
    if not rid_file.exists():
        print(f"SKIP {team_id}: no active release_id in tmp/release-cycle-active/")
        continue

    release_id = rid_file.read_text().strip()
    team_release_ids[team_id] = release_id
    signoff = root / 'sessions' / pm_agent / 'artifacts' / 'release-signoffs' / f"{release_id}.md"

    if signoff.exists():
        print(f"OK   {team_id}: {release_id} already signed off")
        continue

    print(f"RUN  {team_id}: filing signoff for {release_id} ...")
    sys.stdout.flush()
    result = subprocess.run(
        ['bash', str(root / 'scripts' / 'release-signoff.sh'), team_id, release_id],
        capture_output=False,
        cwd=str(root),
    )
    if result.returncode != 0:
        print(f"WARN {team_id}: release-signoff.sh exited {result.returncode} — check Gate 2 evidence")
    else:
        print(f"DONE {team_id}: {release_id} signed off")

# Step 2 — write the orchestrator marker file so _coordinated_push_step() does not re-deploy.
# The marker key matches the combined_key built in orchestrator/run.py _coordinated_push_step().
if team_release_ids:
    combined_key = "__".join(
        re.sub(r'[^A-Za-z0-9._-]', '-', team_release_ids[t['id']])
        for t in sorted(coord_teams, key=lambda x: x['id'])
        if t['id'] in team_release_ids
    )[:120]
    pushed_dir = root / 'tmp' / 'auto-push-dispatched'
    pushed_dir.mkdir(parents=True, exist_ok=True)
    marker = pushed_dir / f"{combined_key}.pushed"
    if not marker.exists():
        marker.write_text(datetime.now(timezone.utc).isoformat() + "\n")
        print(f"MARKER written: {marker.name}")
    else:
        print(f"MARKER already exists: {marker.name}")
PY

echo "=== post-coordinated-push complete ==="
