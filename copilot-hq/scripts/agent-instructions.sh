#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

IDENTITY_FILE="$ROOT_DIR/node-identity.conf"
if [ -f "$IDENTITY_FILE" ]; then
  # shellcheck source=../node-identity.conf
  # shellcheck disable=SC1091
  source "$IDENTITY_FILE"
fi

LOCAL_NODE_INSTRUCTIONS_FILE="$ROOT_DIR/node-instructions.local.md"

AGENT_ID="${1:-}"
if [ -z "$AGENT_ID" ]; then
  echo "Usage: $0 <agent-id>" >&2
  exit 1
fi

CTX="$(
  python3 - "$AGENT_ID" <<'PY'
import sys, re, pathlib, ast

agent_id = sys.argv[1]
text = pathlib.Path("org-chart/agents/agents.yaml").read_text(encoding="utf-8", errors="ignore").splitlines()

in_item = False
role = ""
website = ""

for line in text:
  m = re.match(r"^\s*-\s+id:\s*(.+)\s*$", line)
  if m:
    in_item = (m.group(1).strip() == agent_id)
    continue
  if not in_item:
    continue

  m = re.match(r"^\s*role:\s*(.+)\s*$", line)
  if m and not role:
    role = m.group(1).strip()

  m = re.match(r"^\s*website_scope:\s*(.+)\s*$", line)
  if m and not website:
    try:
      arr = ast.literal_eval(m.group(1).strip())
      if isinstance(arr, list) and arr and arr[0] != '*':
        website = str(arr[0])
    except Exception:
      pass

print(f"{role}\t{website}")
PY
)"

ROLE="$(printf '%s' "$CTX" | awk -F'\t' '{print $1}')"
WEBSITE="$(printf '%s' "$CTX" | awk -F'\t' '{print $2}')"

agent_is_active_on_node() {
  local agent_id="$1"
  local active_agents="${NODE_ACTIVE_AGENTS:-}"
  [ -n "$active_agents" ] || return 1
  for active_agent in $active_agents; do
    if [ "$active_agent" = "$agent_id" ]; then
      return 0
    fi
  done
  return 1
}

echo "== Org-wide =="
cat org-chart/org-wide.instructions.md
echo

if [ -n "$ROLE" ] && [ -f "org-chart/roles/${ROLE}.instructions.md" ]; then
  echo "== Role: ${ROLE} =="
  cat "org-chart/roles/${ROLE}.instructions.md"
  echo
else
  echo "== Role: (unknown) =="
  echo "No role instructions found for agent '${AGENT_ID}' (role='${ROLE}')."
  echo
fi

if [ -n "${WEBSITE:-}" ] && [ -f "org-chart/sites/${WEBSITE}/site.instructions.md" ]; then
  echo "== Site: ${WEBSITE} =="
  cat "org-chart/sites/${WEBSITE}/site.instructions.md"
  echo
fi

if [ -f "org-chart/agents/instructions/${AGENT_ID}.instructions.md" ]; then
  echo "== Seat: ${AGENT_ID} =="
  cat "org-chart/agents/instructions/${AGENT_ID}.instructions.md"
  echo
fi

if [ -f "$IDENTITY_FILE" ]; then
  echo "== Node identity =="
  echo "- node_id: ${NODE_ID:-unknown}"
  echo "- node_role: ${NODE_ROLE:-unknown}"
  if [ -n "${NODE_LABEL:-}" ]; then
    echo "- node_label: ${NODE_LABEL}"
  fi
  if [ -n "${NODE_DEFAULT_AGENT:-}" ]; then
    echo "- node_default_agent: ${NODE_DEFAULT_AGENT}"
  fi
  if [ -n "${NODE_PROJECTS:-}" ]; then
    echo "- node_projects: ${NODE_PROJECTS}"
  fi
  if [ -n "${NODE_ACTIVE_AGENTS:-}" ]; then
    echo "- node_active_agents: ${NODE_ACTIVE_AGENTS}"
    if agent_is_active_on_node "$AGENT_ID"; then
      echo "- active_on_this_node: yes"
    else
      echo "- active_on_this_node: no"
    fi
  fi
  echo "- local_identity_file: ${IDENTITY_FILE}"
  echo
fi

if [ -f "$LOCAL_NODE_INSTRUCTIONS_FILE" ]; then
  echo "== Local node instructions =="
  cat "$LOCAL_NODE_INSTRUCTIONS_FILE"
  echo
fi

echo "== Repo instructions =="
echo "- Follow the target repo's instructions.md (example: forseti.life/.github/instructions/instructions.md)."
