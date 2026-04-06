#!/usr/bin/env bash
set -euo pipefail

# Create post-release process review inbox items for PM + CEO seats.
# Designed to be consumed by agent-exec-loop.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

# ── Input validation ─────────────────────────────────────────────────────────

# Handle --dry-run as $1 BEFORE date validation (special flag, not a date).
DRY_RUN=false
if [[ "${1:-}" == "--dry-run" ]]; then
  DRY_RUN=true
  shift
fi

# Validate $1: must be exactly 8 digits (YYYYMMDD) or unset (defaults to today).
if [ -n "${1:-}" ] && ! [[ "${1}" =~ ^[0-9]{8}$ ]]; then
  echo "Error: first argument must be a date in YYYYMMDD format (got: '${1}')" >&2
  exit 1
fi

DATE_YYYYMMDD="${1:-$(date +%Y%m%d)}"
TOPIC="${2:-improvement-round}"

# ── Derive release site from TOPIC for scope-filtering ──────────────────────
# TOPIC format: improvement-round-<YYYYMMDD>-<site>-<rest>
# We extract the site token after the date prefix, e.g.:
#   improvement-round-20260405-forseti-release-next → site = forseti
#   improvement-round-20260405-dungeoncrawler-release-b → site = dungeoncrawler
# If no site token is present (bare improvement-round or improvement-round-<YYYYMMDD>),
# treat as cross-site (deliver to all active agents).
RELEASE_SITE=""
if [[ "$TOPIC" =~ ^improvement-round-[0-9]{8}-([a-zA-Z0-9_.-]+) ]]; then
  RELEASE_SITE="${BASH_REMATCH[1]}"
fi

# ── Reject non-YYYYMMDD release-id suffixes ──────────────────────────────────
# TOPIC matching improvement-round-<X> where <X> does NOT start with 8 digits
# bypasses the signoff gate entirely. Reject at creation time.
if [[ "$TOPIC" =~ ^improvement-round-(.+)$ ]]; then
  suffix="${BASH_REMATCH[1]}"
  if ! [[ "$suffix" =~ ^[0-9]{8} ]]; then
    echo "Error: TOPIC '${TOPIC}': release-id suffix must start with YYYYMMDD (8 digits), got '${suffix}'" >&2
    exit 1
  fi
fi

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

# ── Build dispatch list filtered by website_scope ────────────────────────────
# Only deliver to an agent if:
#   a) no RELEASE_SITE is set (bare improvement-round → everyone), OR
#   b) the agent's website_scope contains '*' (cross-site seats), OR
#   c) the agent's website_scope contains the RELEASE_SITE token
agent_ids="$(
  FILTER_SITE="$RELEASE_SITE" python3 - <<'PY'
import yaml, os
from pathlib import Path
p = Path('org-chart/agents/agents.yaml')
if not p.exists():
    raise SystemExit(0)
data = yaml.safe_load(p.read_text(encoding='utf-8', errors='ignore'))
filter_site = os.environ.get('FILTER_SITE', '').strip()
for agent in data.get('agents', []):
    if agent.get('paused', False):
        continue
    scopes = agent.get('website_scope', [])
    if not filter_site:
        # No site filter — dispatch to all (legacy / bare improvement-round).
        print(agent['id'])
        continue
    # Wildcard seats always receive.
    if '*' in scopes:
        print(agent['id'])
        continue
    # Site match: any scope entry that starts with the release site token.
    for s in scopes:
        if s.lower().startswith(filter_site.lower()):
            print(agent['id'])
            break
PY
)"

# ── Validate inbox folder name format ────────────────────────────────────────
# Folder name must be: YYYYMMDD-<TOPIC>  where TOPIC starts with "improvement-round".
# Bare YYYYMMDD-improvement-round (no suffix at all) is rejected to prevent
# undifferentiated accumulation.
FOLDER_NAME="${DATE_YYYYMMDD}-${TOPIC}"
if [[ "$TOPIC" == "improvement-round" ]]; then
  echo "Error: TOPIC must include a release-id suffix (e.g. improvement-round-<YYYYMMDD>-<site>-<slug>); bare 'improvement-round' is rejected" >&2
  exit 1
fi
if ! [[ "$FOLDER_NAME" =~ ^[0-9]{8}-improvement-round-.+ ]]; then
  echo "Error: inbox folder name '${FOLDER_NAME}' does not match required format ^[0-9]{8}-improvement-round-.+" >&2
  exit 1
fi

# ── Dry-run output ────────────────────────────────────────────────────────────
if [ "$DRY_RUN" = true ]; then
  echo "DRY-RUN: would dispatch '${FOLDER_NAME}' to:"
  for agent in $agent_ids; do
    echo "  - ${agent}"
  done
  echo "(RELEASE_SITE filter: '${RELEASE_SITE:-<none>}')"
  exit 0
fi

# ── Dispatch ──────────────────────────────────────────────────────────────────
for agent in $agent_ids; do
  inbox_dir="sessions/${agent}/inbox/${FOLDER_NAME}"

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

echo "Created post-release review inbox items for ${FOLDER_NAME} (site filter: '${RELEASE_SITE:-<none>}')" 
