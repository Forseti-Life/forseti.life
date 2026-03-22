#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

STRICT=0
if [ "${1:-}" = "--strict" ]; then
  STRICT=1
fi

fail() {
  echo "VERIFY: FAIL - $*" >&2
  exit 1
}

warn() {
  echo "VERIFY: WARN - $*" >&2
}

pass() {
  echo "VERIFY: OK   - $*"
}

org_enabled="$(./scripts/is-org-enabled.sh 2>/dev/null || echo false)"
[ "$org_enabled" = "true" ] || fail "org automation disabled"
pass "org automation enabled"

backend_mode="${HQ_AGENTIC_BACKEND:-auto}"
copilot_bin="$(command -v copilot 2>/dev/null || true)"
if [ -z "$copilot_bin" ] && [ -x "$HOME/.npm-global/bin/copilot" ]; then
  copilot_bin="$HOME/.npm-global/bin/copilot"
fi
bedrock_script="${BEDROCK_ASSIST_SCRIPT:-$ROOT_DIR/scripts/bedrock-assist.sh}"

case "$backend_mode" in
  auto)
    if [ -n "$copilot_bin" ]; then
      pass "backend(auto): copilot available"
    elif [ -x "$bedrock_script" ]; then
      pass "backend(auto): bedrock assistant available"
    else
      fail "backend(auto): neither copilot nor bedrock assistant available"
    fi
    ;;
  copilot)
    [ -n "$copilot_bin" ] || fail "backend(copilot): copilot CLI not found"
    pass "backend(copilot): copilot CLI available"
    ;;
  bedrock)
    [ -x "$bedrock_script" ] || fail "backend(bedrock): assistant script missing or not executable ($bedrock_script)"
    pass "backend(bedrock): assistant script available"
    ;;
  *)
    fail "invalid HQ_AGENTIC_BACKEND='$backend_mode' (expected auto|copilot|bedrock)"
    ;;
esac

service_active=0
if command -v systemctl >/dev/null 2>&1 && systemctl --user show-environment >/dev/null 2>&1; then
  if systemctl --user is-active --quiet copilot-sessions-hq-orchestrator.service; then
    service_active=1
    pass "systemd orchestrator service active"
  fi
fi

loop_status="$(./scripts/orchestrator-loop.sh status 2>/dev/null || echo not-running)"
loop_active=0
if [[ "$loop_status" == running* ]]; then
  loop_active=1
  pass "orchestrator loop active"
fi

if [ "$service_active" -eq 0 ] && [ "$loop_active" -eq 0 ]; then
  fail "no orchestrator runtime active (systemd service or loop wrapper)"
fi

publish_status="$(./scripts/publish-forseti-agent-tracker-loop.sh status 2>/dev/null || echo not-running)"
[[ "$publish_status" == running* ]] || fail "publish loop not running"
pass "publish loop active"

checkpoint_status="$(./scripts/auto-checkpoint-loop.sh status 2>/dev/null || echo not-running)"
[[ "$checkpoint_status" == running* ]] || fail "auto-checkpoint loop not running"
pass "auto-checkpoint loop active"

release_ctrl=""
if [ -f /var/tmp/copilot-sessions-hq/release-cycle-control.json ]; then
  release_ctrl="/var/tmp/copilot-sessions-hq/release-cycle-control.json"
elif [ -f tmp/release-cycle-control.json ]; then
  release_ctrl="tmp/release-cycle-control.json"
fi

if [ -n "$release_ctrl" ]; then
  release_enabled="$(python3 - <<'PY'
import json
from pathlib import Path
p = Path("/var/tmp/copilot-sessions-hq/release-cycle-control.json")
if not p.exists():
    p = Path("tmp/release-cycle-control.json")
if not p.exists():
    print("unknown")
    raise SystemExit(0)
try:
    d = json.loads(p.read_text(encoding="utf-8"))
except Exception:
    print("invalid")
    raise SystemExit(0)
print("true" if bool(d.get("enabled", True)) else "false")
PY
)"
  if [ "$release_enabled" = "false" ]; then
    if [ "$STRICT" -eq 1 ]; then
      fail "release-cycle automation disabled ($release_ctrl)"
    fi
    warn "release-cycle automation disabled ($release_ctrl)"
  else
    pass "release-cycle automation enabled"
  fi
else
  warn "release-cycle control file not found"
fi

log_file="inbox/responses/orchestrator-latest.log"
if [ ! -f "$log_file" ]; then
  fail "orchestrator latest log missing ($log_file)"
fi
age_secs="$(
  python3 - <<'PY'
import os, time
p = "inbox/responses/orchestrator-latest.log"
print(int(time.time() - os.path.getmtime(p)))
PY
)"
if [ "$age_secs" -gt 300 ]; then
  if [ "$STRICT" -eq 1 ]; then
    fail "orchestrator log stale (${age_secs}s)"
  fi
  warn "orchestrator log appears stale (${age_secs}s)"
else
  pass "orchestrator log freshness ${age_secs}s"
fi

echo "VERIFY: PASS - runtime checks complete"

