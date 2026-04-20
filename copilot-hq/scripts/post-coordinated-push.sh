#!/usr/bin/env bash
# post-coordinated-push.sh — Auto-advance each team's release cycle after a coordinated push.
#
# Usage: bash scripts/post-coordinated-push.sh
#
# Run this immediately after the coordinated git push completes (Gate 4).
# It records a team-scoped release signoff for every coordinated team that has an
# active release in tmp/release-cycle-active/ but has not yet been signed off.
# This advances each team's orchestrator release cycle so the next cycle can begin,
# re-seeds the normal release-cycle handoff for the new current/next pair, and runs
# the CEO boundary health check to keep the next cycle from stalling silently.
#
# Idempotent — safe to re-run.

set -euo pipefail
ROOT_DIR="${HQ_ROOT_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"

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

    def next_release_id_after(release_id: str, team_id: str, current_day: str) -> str:
        suffixes = ["release", "release-next"] + [f"release-{chr(c)}" for c in range(ord("b"), ord("z") + 1)]
        prefix = f"{current_day}-{team_id}-"
        date_part = current_day
        suffix = "release"
        m = re.match(rf"^(\d{{8}})-{re.escape(team_id)}-(.+)$", release_id or "")
        if m:
            date_part = m.group(1)
            suffix = m.group(2)
        try:
            idx = suffixes.index(suffix)
        except ValueError:
            idx = 0
        next_idx = min(idx + 1, len(suffixes) - 1)
        return f"{date_part}-{team_id}-{suffixes[next_idx]}"

    def seed_handoff(team_id: str, current_release: str, next_release: str) -> None:
        seed_result = subprocess.run(
            ['bash', str(root / 'scripts' / 'release-cycle-start.sh'), team_id, current_release, next_release],
            capture_output=True,
            text=True,
            cwd=str(root),
        )
        if seed_result.returncode != 0:
            print(f"WARN {team_id}: release-cycle-start.sh exited {seed_result.returncode} after advance")
            stderr = (seed_result.stderr or "").strip()
            stdout = (seed_result.stdout or "").strip()
            if stderr:
                print(stderr)
            elif stdout:
                print(stdout)
        else:
            print(f"SEED {team_id}: release-cycle-start.sh queued current/next handoff")

    # Step 3 — advance each team's release_id to next_release_id.
    # Runs unconditionally (not tied to marker creation) so a re-run of this
    # script after a pre-existing signoff doesn't skip the advance.
    # Per-team sentinel (<team_id>.advanced) records the new release_id we advanced
    # to; if current release_id already matches, this is a re-run — skip.
    # Use local time to match release-cycle-start.sh which uses `date +%Y%m%d`.
    today = datetime.now().strftime("%Y%m%d")
    for team in sorted(coord_teams, key=lambda t: t['id']):
        team_id = team['id']
        next_rid_file = runtime_dir / f"{team_id}.next_release_id"
        if not next_rid_file.exists():
            print(f"WARN {team_id}: no next_release_id file — skipping release_id advancement")
            continue
        new_current = next_rid_file.read_text(encoding='utf-8').strip()
        if not new_current:
            print(f"WARN {team_id}: next_release_id file is empty — skipping release_id advancement")
            continue
        # Idempotency: skip if we already advanced this team's release_id.
        # The sentinel records the value we wrote as release_id; if the current
        # release_id still matches that sentinel, Step 3 already ran — skip.
        advance_sentinel = pushed_dir / f"{team_id}.advanced"
        if advance_sentinel.exists():
            sentinel_val = advance_sentinel.read_text(encoding='utf-8').strip()
            current_rid = (runtime_dir / f"{team_id}.release_id").read_text(encoding='utf-8').strip()
            if current_rid == sentinel_val:
                print(f"SKIP {team_id}: release_id already advanced to {sentinel_val}")
                seed_handoff(team_id, current_rid, new_current)
                continue
        new_next = next_release_id_after(new_current, team_id, today)
        (runtime_dir / f"{team_id}.release_id").write_text(new_current + "\n", encoding='utf-8')
        (runtime_dir / f"{team_id}.next_release_id").write_text(new_next + "\n", encoding='utf-8')
        (runtime_dir / f"{team_id}.started_at").write_text(
            datetime.now(timezone.utc).isoformat() + "\n", encoding='utf-8'
        )
        advance_sentinel.write_text(new_current + "\n", encoding='utf-8')
        print(f"ADVANCE {team_id}: release_id={new_current} next_release_id={new_next}")
        seed_handoff(team_id, new_current, new_next)
else:
    print("WARNING: no active team releases found — nothing to push")
PY

echo
bash "${ROOT_DIR}/scripts/ceo-release-boundary-health.sh"

echo "=== post-coordinated-push complete ==="
