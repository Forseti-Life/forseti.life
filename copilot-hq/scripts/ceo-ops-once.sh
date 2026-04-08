#!/usr/bin/env bash
set -euo pipefail

# CEO operational cycle (single run).
# - Checks status, blockers, and priority rankings.
# - Emits a concise report to stdout.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"
cd "$ROOT_DIR"

ts="$(date -Iseconds)"

echo "[$ts] Forseti CEO ops cycle"
echo

echo "== Priority rankings =="
python3 - <<'PY'
import pathlib
try:
    import yaml  # type: ignore
except Exception:
    yaml = None
p = pathlib.Path('org-chart/priorities.yaml').read_text(encoding='utf-8')
if yaml:
    data = yaml.safe_load(p) or {}
    pr = (data.get('priorities') or {})
else:
    # Minimal parser: `key: int` lines under `priorities:`
    pr = {}
    in_pr = False
    for line in p.splitlines():
        if line.strip().startswith('#') or not line.strip():
            continue
        if line.strip() == 'priorities:':
            in_pr = True
            continue
        if in_pr and line.startswith('  ') and ':' in line:
            k,v = line.strip().split(':',1)
            pr[k.strip()] = int(v.strip())
items = sorted(pr.items(), key=lambda kv: kv[1], reverse=True)
for k,v in items:
    print(f"- {k}: {v}")
PY

echo
echo "== HQ status =="
./scripts/hq-status.sh

echo
echo "== Blockers (latest per agent outbox) =="
./scripts/hq-blockers.sh | head -n 200

echo
echo "== CEO actions suggested =="
# Simple triage suggestions based on current status.
blocked_count=$(./scripts/hq-blockers.sh count 2>/dev/null || echo 0)
if [ "${blocked_count:-0}" -gt 0 ]; then
  echo "- Unblock: review supervisor escalation inbox items under sessions/<supervisor>/inbox/*needs-*/"
fi

matrix_noncompliant_count=$(./scripts/escalation-matrix-compliance.sh count 2>/dev/null || echo 0)
if [ "${matrix_noncompliant_count:-0}" -gt 0 ]; then
    echo "- Escalation compliance: ${matrix_noncompliant_count} blocked/needs-info item(s) are missing 'Matrix issue type'; request matrix-mapped escalation rewrites."
fi

# Prioritize agent-management: ensure tracker PM/Dev/QA have work if backlog exists.
pm_backlog=$(find sessions/pm-forseti-agent-tracker/inbox -mindepth 1 -maxdepth 1 -type d ! -name "_archived" 2>/dev/null | wc -l | awk '{print $1}')
if [ "${pm_backlog:-0}" -gt 0 ]; then
  echo "- Agent-management: pm-forseti-agent-tracker has ${pm_backlog} inbox item(s); ensure outbox artifacts are being produced and prioritize this stream."
fi

echo "- If any team is intentionally deprioritized, record that in the work item update (with reason + next review time)."

echo
echo "== Idle work seeding (DISABLED) =="
echo "(skipped)"
