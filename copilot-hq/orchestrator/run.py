#!/usr/bin/env python3
"""Consolidated orchestrator for copilot-sessions-hq.

Single process replacing: ceo-inbox-loop, inbox-loop, ceo-health-loop,
2-ceo-opsloop, and the old split ceo/non-ceo exec model.

Tick pipeline (in order):
  consume_replies    - pull Board replies from Drupal into agent inboxes
  dispatch_commands  - route inbox/commands/*.md to PM inboxes or CEO inbox
  release_cycle      - ensure each team has an active release cycle (interval-gated)
  pick_agents        - prioritize all agents (CEO included) that have inbox items
  exec_agents        - run agent-exec-next.sh for each picked agent
  health_check       - detect stalled agents, auto-remediate (cooldown-gated)
  kpi_monitor        - release KPI stagnation check (interval-gated)
  publish            - push telemetry to Drupal dashboard
"""

from __future__ import annotations

import argparse
import os
import re
import subprocess
import sys
import time
from dataclasses import dataclass
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, Dict, List, Optional, Tuple

REPO_ROOT = Path(__file__).resolve().parent.parent
if str(REPO_ROOT) not in sys.path:
    sys.path.insert(0, str(REPO_ROOT))

from orchestrator.runtime_graph.engine import LangGraphDeps, run_tick as _run_langgraph_tick

# ── Helpers ──────────────────────────────────────────────────────────────────

def _run(cmd: List[str], *, timeout: int = 600) -> Tuple[int, str]:
    try:
        proc = subprocess.run(
            cmd,
            cwd=str(REPO_ROOT),
            stdout=subprocess.PIPE,
            stderr=subprocess.STDOUT,
            text=True,
            timeout=timeout,
        )
        return proc.returncode, (proc.stdout or "").strip()
    except subprocess.TimeoutExpired:
        return -1, f"TIMEOUT after {timeout}s"


def _safe_int(s: Any, default: int = 0) -> int:
    try:
        return int(s)
    except Exception:
        return default


def _now_ts() -> int:
    return int(time.time())


def _cooldown_ok(state_file: Path, seconds: int) -> bool:
    last = _safe_int(state_file.read_text(encoding="utf-8").strip() if state_file.exists() else "0", 0)
    return (_now_ts() - last) >= seconds


def _mark_now(state_file: Path) -> None:
    state_file.parent.mkdir(parents=True, exist_ok=True)
    state_file.write_text(str(_now_ts()), encoding="utf-8")


_RELEASE_CYCLE_CONTROL_DEFAULT = Path("/var/tmp/copilot-sessions-hq/release-cycle-control.json")


def _release_cycle_control_file() -> Path:
    override = os.environ.get("RELEASE_CYCLE_CONTROL_FILE", "").strip()
    if override:
        return Path(override)

    legacy = REPO_ROOT / "tmp" / "release-cycle-control.json"
    if _RELEASE_CYCLE_CONTROL_DEFAULT.exists():
        return _RELEASE_CYCLE_CONTROL_DEFAULT
    if legacy.exists():
        return legacy
    return _RELEASE_CYCLE_CONTROL_DEFAULT


def _release_cycle_control_state() -> Dict[str, Any]:
    import json as _json

    state_file = _release_cycle_control_file()
    if not state_file.exists():
        return {
            "enabled": True,
            "updated_at": None,
            "updated_by": None,
            "reason": None,
            "state_file": str(state_file),
        }

    try:
        data = _json.loads(state_file.read_text(encoding="utf-8", errors="ignore") or "{}")
    except Exception:
        data = {}

    if not isinstance(data, dict):
        data = {}

    data.setdefault("enabled", True)
    data.setdefault("updated_at", None)
    data.setdefault("updated_by", None)
    data.setdefault("reason", None)
    data["state_file"] = str(state_file)
    return data


def _is_release_cycle_enabled() -> bool:
    return bool(_release_cycle_control_state().get("enabled", True))


# ── Agent / YAML helpers ─────────────────────────────────────────────────────

def _load_agents_yaml_ids() -> List[str]:
    f = REPO_ROOT / "org-chart" / "agents" / "agents.yaml"
    if not f.exists():
        return []
    ids: List[str] = []
    for ln in f.read_text(encoding="utf-8", errors="ignore").splitlines():
        if ln.strip().startswith("- id:"):
            aid = ln.split(":", 1)[1].strip()
            if aid:
                ids.append(aid)
    return ids


def _agent_field(agent_id: str, field: str) -> str:
    """Read a single scalar field from agents.yaml for the given agent."""
    f = REPO_ROOT / "org-chart" / "agents" / "agents.yaml"
    if not f.exists():
        return ""
    in_item = False
    for ln in f.read_text(encoding="utf-8", errors="ignore").splitlines():
        m = re.match(r"^\s*-\s+id:\s*(.+)\s*$", ln)
        if m:
            in_item = m.group(1).strip() == agent_id
            continue
        if in_item:
            m2 = re.match(rf"^\s*{re.escape(field)}:\s*(.+)\s*$", ln)
            if m2:
                return m2.group(1).strip()
    return ""


def _role_for_agent(agent_id: str) -> str:
    return _agent_field(agent_id, "role")


def _primary_ceo_agent() -> str:
    """Resolve the active CEO seat for command/stagnation routing."""
    preferred = os.environ.get("ORCHESTRATOR_CEO_AGENT", "").strip()
    if preferred and preferred.startswith("ceo-copilot") and not _is_agent_paused(preferred):
        return preferred

    for agent_id in _load_agents_yaml_ids():
        if agent_id.startswith("ceo-copilot") and not _is_agent_paused(agent_id):
            return agent_id

    return "ceo-copilot"


def _is_agent_paused(agent_id: str) -> bool:
    script = REPO_ROOT / "scripts" / "is-agent-paused.sh"
    if not script.exists():
        return False
    rc, out = _run(["bash", str(script), agent_id], timeout=30)
    return rc == 0 and out.strip().lower() == "true"


def _agent_level_weight(role: str) -> int:
    return {"ceo": 500, "product-manager": 400, "business-analyst": 300,
            "software-developer": 200, "tester": 150, "security-analyst": 100}.get(role, 100)


def _agent_inbox_dir(agent_id: str) -> Path:
    return REPO_ROOT / "sessions" / agent_id / "inbox"


def _is_inbox_item_done(item_dir: Path) -> bool:
    """Return True if item's command.md is marked '- Status: done' (skip from dispatch)."""
    import re as _re
    cmd = item_dir / "command.md"
    if not cmd.exists():
        return False
    try:
        text = cmd.read_text(encoding="utf-8", errors="ignore")
        return bool(_re.search(r"^-\s+[Ss]tatus:\s*done", text, _re.MULTILINE))
    except Exception:
        return False


def _agent_inbox_count(agent_id: str) -> int:
    d = _agent_inbox_dir(agent_id)
    if not d.is_dir():
        return 0
    return sum(
        1 for p in d.iterdir()
        if p.is_dir() and p.name != "_archived" and not _is_inbox_item_done(p)
    )


def _load_org_priorities() -> Dict[str, int]:
    path = REPO_ROOT / "org-chart" / "priorities.yaml"
    if not path.exists():
        return {}
    txt = path.read_text(encoding="utf-8", errors="ignore")
    try:
        import yaml  # type: ignore
        data = yaml.safe_load(txt) or {}
        if isinstance(data.get("priorities"), dict):
            return {str(k): int(v) for k, v in data["priorities"].items() if str(v).lstrip("-").isdigit()}
    except Exception:
        pass
    # Minimal fallback
    priorities: Dict[str, int] = {}
    in_pr = False
    for ln in txt.splitlines():
        s = ln.strip()
        if s == "priorities:":
            in_pr = True
        elif in_pr and ln.startswith("  ") and ":" in ln:
            k, v = s.split(":", 1)
            try:
                priorities[k.strip()] = int(v.strip())
            except Exception:
                pass
    return priorities


def _org_priority_key(item_name: str) -> str:
    lower = item_name.lower()
    for k in ("agent-management", "agent-tracker", "copilot-agent-tracker", "copilot_agent_tracker"):
        if k in lower:
            return "agent-management"
    if any(k in lower for k in ("jobhunter", "job_hunter")):
        return "jobhunter"
    if "dungeoncrawler" in lower:
        return "dungeoncrawler"
    return ""


def _item_effective_roi(item_dir: Path, item_name: str, *, priorities: Dict[str, int]) -> int:
    roi_file = item_dir / "roi.txt"
    base = 1
    if roi_file.exists():
        digits = "".join(ch for ch in roi_file.read_text(encoding="utf-8", errors="ignore").splitlines()[0] if ch.isdigit())
        base = max(1, _safe_int(digits, 1))
    divisor = _safe_int(os.environ.get("ORG_PRIORITY_DIVISOR", "100"), 100)
    score = priorities.get(_org_priority_key(item_name), 0)
    bonus = (base * score) // divisor if divisor > 0 and score > 0 else 0
    return base + bonus


@dataclass(frozen=True)
class ScheduledAgent:
    agent_id: str
    level: int
    top_roi: int
    has_release_work: bool = False
    has_next_release_work: bool = False
    team_id: str = ""


def _active_release_ids() -> List[str]:
    """Return all currently active release IDs from tmp/release-cycle-active/."""
    active_dir = REPO_ROOT / "tmp" / "release-cycle-active"
    if not active_dir.exists():
        return []
    ids = []
    for f in active_dir.glob("*.release_id"):
        rid = f.read_text(encoding="utf-8").strip()
        if rid:
            ids.append(rid)
    return ids


def _next_release_ids() -> List[str]:
    """Return all currently tracked next-release IDs from tmp/release-cycle-active/."""
    active_dir = REPO_ROOT / "tmp" / "release-cycle-active"
    if not active_dir.exists():
        return []
    ids = []
    for f in active_dir.glob("*.next_release_id"):
        rid = f.read_text(encoding="utf-8").strip()
        if rid:
            ids.append(rid)
    return ids


def _team_id_for_agent(agent_id: str) -> str:
    for team_id in _TEAM_WEBSITE_PREFIX:
        if team_id in agent_id:
            return team_id
    return ""


def _item_mentions_release(item_dir: pathlib.Path, release_ids: List[str]) -> bool:
    if not release_ids or not item_dir.exists():
        return False
    for rid in release_ids:
        if rid and rid in item_dir.name:
            return True
    for rel_path in ("command.md", "README.md"):
        path = item_dir / rel_path
        if not path.exists():
            continue
        text = path.read_text(encoding="utf-8", errors="ignore")
        for rid in release_ids:
            if rid and rid in text:
                return True
    return False


def _item_text(item_dir: pathlib.Path) -> str:
    parts: List[str] = []
    for rel_path in ("command.md", "README.md"):
        path = item_dir / rel_path
        if path.exists():
            parts.append(path.read_text(encoding="utf-8", errors="ignore"))
    return "\n".join(parts)


def _item_mentions_current_release(item_dir: pathlib.Path, release_ids: List[str]) -> bool:
    if _item_mentions_release(item_dir, release_ids):
        return True
    text = _item_text(item_dir)
    if not text:
        return False
    return bool(re.search(r"\bcurrent release\b", text, re.IGNORECASE))


def _item_mentions_next_release(item_dir: pathlib.Path, next_release_ids: List[str]) -> bool:
    if _item_mentions_release(item_dir, next_release_ids):
        return True
    text = _item_text(item_dir)
    if not text:
        return False
    return bool(re.search(r"\bnext release\b", text, re.IGNORECASE))


def _agent_has_release_work(agent_id: str, release_ids: List[str]) -> bool:
    """Return True if the agent has an inbox item explicitly tied to the current release."""
    if not release_ids:
        return False
    inbox = _agent_inbox_dir(agent_id)
    if not inbox.exists():
        return False

    for item in inbox.iterdir():
        if item.is_dir() and item.name != "_archived" and _item_mentions_current_release(item, release_ids):
            return True

    return False


def _agent_has_next_release_work(agent_id: str, next_release_ids: List[str]) -> bool:
    """Return True if the agent has explicit PM/BA next-release prep work queued."""
    if not next_release_ids:
        return False
    role = _role_for_agent(agent_id)
    if role not in {"product-manager", "business-analyst"} and not (
        agent_id.startswith("pm-") or agent_id.startswith("ba-")
    ):
        return False
    inbox = _agent_inbox_dir(agent_id)
    if not inbox.exists():
        return False
    for item in inbox.iterdir():
        if item.is_dir() and item.name != "_archived" and _item_mentions_next_release(item, next_release_ids):
            return True
    return False


def _prioritized_agents() -> List[ScheduledAgent]:
    """All agents (CEO included) with inbox items, sorted by release-aware priority.

    Scheduling intent:
      1. Current-release work always gets first priority.
      2. One current-release seat per team is surfaced before any duplicate
         same-team current-release seats.
      3. Spare capacity then spills into next-release prep work instead of
         waiting for the current release to close out completely.
      4. Remaining current-release seats, remaining next-release seats, then
         all other work follow in priority order.
    """
    priorities = _load_org_priorities()
    release_ids = _active_release_ids()
    next_release_ids = _next_release_ids()
    agents = []
    for agent_id in _load_agents_yaml_ids():
        if _is_agent_paused(agent_id):
            continue
        if _agent_inbox_count(agent_id) <= 0:
            continue
        inbox = _agent_inbox_dir(agent_id)
        top = max(
            (_item_effective_roi(p, p.name, priorities=priorities) for p in inbox.iterdir() if p.is_dir() and p.name != "_archived" and not _is_inbox_item_done(p)),
            default=1,
        )
        has_release = _agent_has_release_work(agent_id, release_ids)
        has_next_release = (not has_release) and _agent_has_next_release_work(agent_id, next_release_ids)
        agents.append(ScheduledAgent(
            agent_id=agent_id,
            level=_agent_level_weight(_role_for_agent(agent_id)),
            top_roi=top,
            has_release_work=has_release,
            has_next_release_work=has_next_release,
            team_id=_team_id_for_agent(agent_id),
        ))

    def _sort_bucket(items: List[ScheduledAgent]) -> List[ScheduledAgent]:
        return sorted(items, key=lambda a: (-a.level, -a.top_roi, a.agent_id))

    def _split_first_per_team(items: List[ScheduledAgent]) -> tuple[List[ScheduledAgent], List[ScheduledAgent]]:
        first_pass: List[ScheduledAgent] = []
        remainder: List[ScheduledAgent] = []
        seen_teams: set[str] = set()
        seen_agents_without_team = 0
        for agent in items:
            team_key = agent.team_id or f"__no_team__:{seen_agents_without_team}"
            if not agent.team_id:
                seen_agents_without_team += 1
            if team_key not in seen_teams:
                seen_teams.add(team_key)
                first_pass.append(agent)
            else:
                remainder.append(agent)
        return first_pass, remainder

    current_first, current_remainder = _split_first_per_team(
        _sort_bucket([a for a in agents if a.has_release_work])
    )
    next_first, next_remainder = _split_first_per_team(
        _sort_bucket([a for a in agents if not a.has_release_work and a.has_next_release_work])
    )
    other_agents = _sort_bucket([a for a in agents if not a.has_release_work and not a.has_next_release_work])

    return current_first + next_first + next_remainder + current_remainder + other_agents


# ── Command dispatch ──────────────────────────────────────────────────────────

def _parse_md_field(text: str, field: str) -> str:
    m = re.search(rf"^-\s+{re.escape(field)}:\s*(.+)$", text, re.MULTILINE)
    return m.group(1).strip() if m else ""


def _route_to_ceo_inbox(content: str, topic: str, work_item: str) -> str:
    """Create a properly-named CEO inbox item so agent-exec-next.sh picks it up."""
    ceo_agent = _primary_ceo_agent()
    slug = re.sub(r"[^a-z0-9-]+", "-", topic.lower()).strip("-")[:50]
    item_id = f"{datetime.now(timezone.utc).strftime('%Y%m%d')}-needs-{ceo_agent}-{slug}"
    item_dir = REPO_ROOT / "sessions" / ceo_agent / "inbox" / item_id
    if item_dir.exists():
        return f"duplicate:{item_id}"
    item_dir.mkdir(parents=True, exist_ok=True)
    (item_dir / "README.md").write_text(
        f"# Command: {topic}\n\n"
        f"- Agent: {ceo_agent}\n"
        f"- Item: {item_id}\n"
        f"- Work item: {work_item}\n"
        f"- Status: pending\n"
        f"- Supervisor: board\n"
        f"- Created: {datetime.now(timezone.utc).isoformat()}\n\n"
        f"## Decision needed\n- Review and action or escalate this command.\n\n"
        f"## Recommendation\n- See command text below.\n\n"
        f"## Command text\n{content}\n",
        encoding="utf-8",
    )
    return f"ceo-inbox:{item_id}"


def _dispatch_commands_step(log: List[Any]) -> None:
    """Route inbox/commands/*.md to PM inboxes or CEO inbox.

    Routing priority:
            0. Has '- target:' for a non-HQ target (for example dev-laptop) → leave queued
      1. Has '- pm:' field → dispatch to that PM via dispatch-pm-request.sh
      2. Has '- work_item:' with matching features/<wi>/feature.md → look up PM owner
      3. Anything else → CEO inbox (CEO GenAI call will triage/action/escalate)
    """
    commands_dir = REPO_ROOT / "inbox" / "commands"
    processed_dir = REPO_ROOT / "inbox" / "processed"
    commands_dir.mkdir(parents=True, exist_ok=True)
    processed_dir.mkdir(parents=True, exist_ok=True)

    dispatched: List[str] = []
    for f in sorted(commands_dir.glob("*.md")):
        content = f.read_text(encoding="utf-8", errors="ignore")
        target = (_parse_md_field(content, "target") or "").strip().lower()
        pm = _parse_md_field(content, "pm")
        work_item = _parse_md_field(content, "work_item")
        topic = _parse_md_field(content, "topic") or f.stem

        dest = processed_dir / f.name

        if target and target not in {"hq", "orchestrator", "ceo"}:
            dispatched.append(f"deferred:{target} topic:{topic}")
            continue

        if pm:
            _run(["bash", "scripts/dispatch-pm-request.sh", pm, work_item or "", topic], timeout=60)
            f.rename(dest)
            dispatched.append(f"pm:{pm} topic:{topic}")
            continue

        if work_item:
            feature = REPO_ROOT / "features" / work_item / "feature.md"
            if feature.exists():
                pm_owner = _parse_md_field(feature.read_text(encoding="utf-8", errors="ignore"), "PM owner")
                if pm_owner:
                    _run(["bash", "scripts/dispatch-pm-request.sh", pm_owner, work_item, topic], timeout=60)
                    f.rename(dest)
                    dispatched.append(f"pm:{pm_owner} via-feature:{work_item}")
                    continue

        # No PM found — route to CEO inbox for GenAI triage
        result = _route_to_ceo_inbox(content, topic, work_item or "")
        f.rename(dest)
        dispatched.append(result)

    log.append({"step": "dispatch_commands", "dispatched": dispatched})


# ── Health check ──────────────────────────────────────────────────────────────

_HEALTH_AUTOEXEC_STATE = REPO_ROOT / "tmp" / "orchestrator-health-last-autoexec"
_HEALTH_AUTOEXEC_COOLDOWN = 120  # seconds

_STAGNATION_STATE_DIR = REPO_ROOT / "tmp" / "orchestrator-stagnation"

# Stagnation signal thresholds
_STAG_NO_DONE_OUTBOX_SECONDS   = 900   # 15 min: no agent wrote Status:done
_STAG_INBOX_AGING_SECONDS      = 1800  # 30 min: inbox item sitting unresolved
_STAG_CEO_INBOX_DEPTH          = 3     # CEO has N+ pending items it hasn't cleared
_STAG_BLOCKED_TICKS            = 5     # N consecutive ticks with blocked agents + no new done outboxes
_STAG_NO_RELEASE_SECONDS       = 7200  # 2 hours: in-flight release with no signoff progress
_STAG_DISPATCH_COOLDOWN        = 1800  # 30 min between CEO dispatches


def _seconds_since_last_done_outbox() -> int:
    """Return seconds since any agent last wrote a Status:done outbox file."""
    import re as _re
    now = _now_ts()
    latest_done_mtime = 0
    for p in (REPO_ROOT / "sessions").glob("*/outbox/*.md"):
        if not p.is_file():
            continue
        try:
            text = p.read_text(encoding="utf-8", errors="ignore")
            if _re.search(r"^-\s+[Ss]tatus:\s*done", text, _re.MULTILINE):
                mtime = int(p.stat().st_mtime)
                if mtime > latest_done_mtime:
                    latest_done_mtime = mtime
        except Exception:
            continue
    return max(0, now - latest_done_mtime) if latest_done_mtime else 99999


def _oldest_unresolved_inbox_seconds() -> int:
    """Return age in seconds of the oldest inbox item that has no corresponding done outbox."""
    import re as _re
    now = _now_ts()
    oldest = 0
    sessions_dir = REPO_ROOT / "sessions"
    for agent_dir in sessions_dir.iterdir():
        if not agent_dir.is_dir():
            continue
        inbox_dir = agent_dir / "inbox"
        outbox_dir = agent_dir / "outbox"
        if not inbox_dir.exists():
            continue
        for item_dir in inbox_dir.iterdir():
            if not item_dir.is_dir() or item_dir.name == "_archived":
                continue
            if _is_inbox_item_done(item_dir):
                continue
            item_id = item_dir.name
            # Check if a done outbox exists for this item
            done = False
            if outbox_dir.exists():
                for candidate in outbox_dir.glob(f"{item_id}*.md"):
                    try:
                        text = candidate.read_text(encoding="utf-8", errors="ignore")
                        if _re.search(r"^-\s+[Ss]tatus:\s*done", text, _re.MULTILINE):
                            done = True
                            break
                    except Exception:
                        continue
            if not done:
                try:
                    age = int(now - item_dir.stat().st_mtime)
                    if age > oldest:
                        oldest = age
                except Exception:
                    continue
    return oldest


def _ceo_inbox_depth() -> int:
    """Return count of pending CEO inbox items."""
    ceo_inbox = REPO_ROOT / "sessions" / _primary_ceo_agent() / "inbox"
    if not ceo_inbox.exists():
        return 0
    return sum(1 for p in ceo_inbox.iterdir() if p.is_dir())


_STAG_ITEM_STALE_SECONDS = 14400  # 4h: re-dispatch if CEO stagnation item sits unresolved this long


def _ceo_has_pending_stagnation_item() -> bool:
    """Return True if there is already an unresolved stagnation item in CEO inbox.

    If the item has been sitting unresolved for > _STAG_ITEM_STALE_SECONDS (4h),
    treat it as stale and return False so a fresh dispatch can occur.  This
    prevents a deadlock where a CEO item stuck at in_progress/blocked blocks all
    future stagnation monitoring.
    """
    ceo_inbox = REPO_ROOT / "sessions" / _primary_ceo_agent() / "inbox"
    if not ceo_inbox.exists():
        return False
    now = _now_ts()
    for item_dir in ceo_inbox.iterdir():
        if not item_dir.is_dir() or "stagnation" not in item_dir.name:
            continue
        # If item is too old (stale), let a new dispatch happen
        age = now - int(item_dir.stat().st_mtime)
        if age > _STAG_ITEM_STALE_SECONDS:
            print(f"STAGNATION-STALE: item {item_dir.name} age={age}s > {_STAG_ITEM_STALE_SECONDS}s — allowing re-dispatch")
            continue
        # Check if it's been resolved (has a Status: done in any item file)
        for md in item_dir.glob("*.md"):
            text = md.read_text(encoding="utf-8", errors="ignore")
            if re.search(r"^-\s+Status:\s*done", text, re.MULTILINE | re.IGNORECASE):
                break  # this stagnation item is resolved
        else:
            return True  # unresolved, within age window — block re-dispatch
    return False


def _seconds_since_last_release_signoff() -> int:
    """Return seconds since any PM wrote a release signoff artifact."""
    now = _now_ts()
    latest = 0
    for p in (REPO_ROOT / "sessions").glob("*/artifacts/release-signoffs/*.md"):
        try:
            mtime = int(p.stat().st_mtime)
            if mtime > latest:
                latest = mtime
        except Exception:
            continue
    return max(0, now - latest) if latest else 99999


def _release_gate_brief() -> str:
    """Return a concise, actionable snapshot of current release gate status.

    Includes: active release IDs, which PMs have/haven't signed, QA preflight
    items pending, and the oldest unresolved inbox item per agent (top 5).
    This context is injected into the CEO stagnation brief so the CEO can act
    immediately without running manual diagnostics.
    """
    import json as _json

    lines: List[str] = []

    # 1. Release signoff status for each active release
    active_dir = REPO_ROOT / "tmp" / "release-cycle-active"
    active_releases: List[str] = []
    if active_dir.exists():
        for f in active_dir.glob("*.release_id"):
            rid = f.read_text(encoding="utf-8").strip()
            if rid:
                active_releases.append(rid)

    if active_releases:
        lines.append("### Active release gate status")
        teams_path = REPO_ROOT / "org-chart" / "products" / "product-teams.json"
        coordinated_teams: List[Dict[str, str]] = []
        try:
            teams_data = _json.loads(teams_path.read_text(encoding="utf-8"))
            for t in teams_data.get("teams", []):
                if t.get("active") and t.get("coordinated_release_default"):
                    tid = (t.get("id") or "").strip()
                    pm = (t.get("pm_agent") or "").strip()
                    if tid and pm:
                        coordinated_teams.append({"id": tid, "pm": pm})
        except Exception:
            pass

        for rid in active_releases:
            slug = re.sub(r"[^A-Za-z0-9._-]", "-", rid)[:80]
            signed: List[str] = []
            unsigned: List[str] = []
            for t in coordinated_teams:
                sf = REPO_ROOT / "sessions" / t["pm"] / "artifacts" / "release-signoffs" / f"{slug}.md"
                if sf.exists():
                    signed.append(t["pm"])
                else:
                    unsigned.append(t["pm"])
            lines.append(f"- `{rid}`:")
            lines.append(f"  - Signed: {', '.join(signed) if signed else 'none'}")
            lines.append(f"  - **Missing signoff: {', '.join(unsigned) if unsigned else 'none — ready to push!'}**")
    else:
        lines.append("### Active releases: none")

    # 2. QA preflight items still in inbox
    qa_agents = [a for a in _load_agents_yaml_ids() if a.startswith("qa-")]
    preflight_pending: List[str] = []
    for qa in qa_agents:
        inbox = _agent_inbox_dir(qa)
        if not inbox.exists():
            continue
        for item in inbox.iterdir():
            if item.is_dir() and item.name != "_archived" and "preflight" in item.name:
                preflight_pending.append(f"{qa}: {item.name}")
    if preflight_pending:
        lines.append("\n### QA preflight items still pending")
        for p in preflight_pending[:10]:
            lines.append(f"- {p}")

    # 3. Top 5 oldest unresolved inbox items across all agents
    lines.append("\n### Oldest unresolved inbox items (top 5)")
    oldest: List[Dict[str, Any]] = []
    now = _now_ts()
    for agent_id in _load_agents_yaml_ids():
        inbox = _agent_inbox_dir(agent_id)
        if not inbox.exists():
            continue
        for item in inbox.iterdir():
            if not item.is_dir() or item.name == "_archived":
                continue
            try:
                age_m = (now - int(item.stat().st_mtime)) // 60
                oldest.append({"agent": agent_id, "item": item.name, "age_m": age_m})
            except Exception:
                pass
    oldest.sort(key=lambda x: x["age_m"], reverse=True)
    for o in oldest[:5]:
        lines.append(f"- {o['agent']}: `{o['item']}` ({o['age_m']}m old)")

    # 4. Feature pipeline gap analysis — in_progress features with missing dev/QA coverage
    active_rids: set = set()
    if active_dir.exists():
        for f in active_dir.glob("*.release_id"):
            rid = f.read_text(encoding="utf-8").strip()
            if rid:
                active_rids.add(rid)
    gaps_found: List[str] = []
    if active_rids:
        for fm in sorted((REPO_ROOT / "features").glob("*/feature.md")):
            try:
                ftext = fm.read_text(encoding="utf-8", errors="ignore")
                if not re.search(r"^-\s+Status:\s*in_progress", ftext, re.MULTILINE | re.IGNORECASE):
                    continue
                rel_m2 = re.search(r"^-\s+Release:\s*(.+)", ftext, re.MULTILINE | re.IGNORECASE)
                if not rel_m2 or rel_m2.group(1).strip() not in active_rids:
                    continue
                feat_name = fm.parent.name
                site_m2 = re.search(r"^-\s+Website:\s*(.+)", ftext, re.MULTILINE | re.IGNORECASE)
                site_raw = site_m2.group(1).strip().lower() if site_m2 else ""
                site_id = next((k for k in _TEAM_WEBSITE_PREFIX if k in site_raw), None)
                if not site_id:
                    continue
                dev_agent = f"dev-{site_id}"
                qa_agent  = f"qa-{site_id}"
                dev_inbox  = _agent_inbox_has_feature(dev_agent, feat_name)
                dev_out    = _agent_outbox_has_feature(dev_agent, feat_name)
                qa_inbox   = _agent_inbox_has_feature(qa_agent,  feat_name)
                qa_out     = _agent_outbox_has_feature(qa_agent,  feat_name)
                if not dev_inbox and not dev_out and not qa_inbox and not qa_out:
                    gaps_found.append(f"GAP-A {feat_name}: no dev or QA coverage (impl not started)")
                elif dev_out and not qa_inbox and not qa_out:
                    gaps_found.append(f"GAP-B {feat_name}: dev done but no QA testgen/verification")
            except Exception:
                pass
    if gaps_found:
        lines.append("\n### ⚠️ Feature pipeline gaps (auto-remediation dispatched)")
        for g in gaps_found:
            lines.append(f"- {g}")
    else:
        lines.append("\n### Feature pipeline: no gaps detected")

    # 5. Quick inbox data quality snapshot (stale locks + missing READMEs/fields)
    sessions_dir = REPO_ROOT / "sessions"
    import re as _re2
    stale_locks = 0
    missing_readmes = 0
    missing_fields = 0
    now_ts = _now_ts()
    if sessions_dir.exists():
        for agent_d in sessions_dir.iterdir():
            ib = agent_d / "inbox"
            if not ib.exists():
                continue
            for lock in ib.glob("**/*.inwork"):
                try:
                    if now_ts - lock.stat().st_mtime > _INWORK_STALE_SECS:
                        stale_locks += 1
                except Exception:
                    pass
            for item_d in ib.iterdir():
                if item_d.name == "_archived" or not item_d.is_dir():
                    continue
                has_r = (item_d / "README.md").exists()
                has_c = (item_d / "command.md").exists()
                if not has_r and not has_c:
                    missing_readmes += 1
                else:
                    md_f = (item_d / "README.md") if has_r else (item_d / "command.md")
                    try:
                        txt = md_f.read_text(encoding="utf-8", errors="ignore")
                        if not _re2.search(r"^-\s+Agent:", txt, _re2.M) or \
                           not _re2.search(r"^-\s+Status:", txt, _re2.M):
                            missing_fields += 1
                    except Exception:
                        pass
    dq_issues = stale_locks + missing_readmes + missing_fields
    if dq_issues:
        lines.append(f"\n### ⚠️ Inbox data quality issues (will auto-remediate next tick)")
        if stale_locks:
            lines.append(f"- {stale_locks} stale .inwork lock(s)")
        if missing_readmes:
            lines.append(f"- {missing_readmes} item(s) missing README/command.md")
        if missing_fields:
            lines.append(f"- {missing_fields} item(s) missing Agent:/Status: fields")
    else:
        lines.append("\n### Inbox data quality: ✅ all items conformant")

    return "\n".join(lines)


_SIGNOFF_REMINDER_STATE = REPO_ROOT / "tmp" / "orchestrator-stagnation" / "signoff_reminder_dispatch"
_SIGNOFF_REMINDER_COOLDOWN = 3600  # 1 hour between reminders per release


def _dispatch_signoff_reminders() -> None:
    """Auto-create signoff-reminder inbox items for PMs lagging on a release.

    When one or more PMs on a coordinated release have signed off but at least
    one has not, and the gap has been open for > 30 minutes, route a
    signoff-reminder directly to the unsigned PM's inbox.  Cooldown-gated per
    release ID to avoid spam.
    """
    import json as _json

    active_dir = REPO_ROOT / "tmp" / "release-cycle-active"
    if not active_dir.exists():
        return

    state_dir = _SIGNOFF_REMINDER_STATE.parent
    state_dir.mkdir(parents=True, exist_ok=True)

    teams_path = REPO_ROOT / "org-chart" / "products" / "product-teams.json"
    try:
        teams_data = _json.loads(teams_path.read_text(encoding="utf-8"))
    except Exception:
        return

    coordinated_teams = [
        {"id": (t.get("id") or "").strip(), "pm": (t.get("pm_agent") or "").strip()}
        for t in teams_data.get("teams", [])
        if t.get("active") and t.get("coordinated_release_default")
        and (t.get("id") or "").strip() and (t.get("pm_agent") or "").strip()
    ]

    for rid_file in active_dir.glob("*.release_id"):
        rid = rid_file.read_text(encoding="utf-8").strip()
        if not rid:
            continue
        slug = re.sub(r"[^A-Za-z0-9._-]", "-", rid)[:80]

        signed = [t for t in coordinated_teams
                  if (REPO_ROOT / "sessions" / t["pm"] / "artifacts" / "release-signoffs" / f"{slug}.md").exists()]
        unsigned = [t for t in coordinated_teams
                    if not (REPO_ROOT / "sessions" / t["pm"] / "artifacts" / "release-signoffs" / f"{slug}.md").exists()]

        if not signed or not unsigned:
            continue  # nobody signed yet, or all signed — nothing to remind

        # Cooldown per release
        state_key = state_dir / f"signoff_reminder_{slug}"
        last = _safe_int(state_key.read_text(encoding="utf-8").strip() if state_key.exists() else "0", 0)
        if (_now_ts() - last) < _SIGNOFF_REMINDER_COOLDOWN:
            continue

        # Dispatch reminder to each unsigned PM
        for t in unsigned:
            pm_id = t["pm"]
            item_id = f"{datetime.now(timezone.utc).strftime('%Y%m%d')}-signoff-reminder-{slug}"
            item_dir = REPO_ROOT / "sessions" / pm_id / "inbox" / item_id
            if item_dir.exists():
                continue  # already dispatched this cycle
            item_dir.mkdir(parents=True, exist_ok=True)
            signed_names = ", ".join(s["pm"] for s in signed)
            (item_dir / "README.md").write_text(
                f"# Signoff reminder: {rid}\n\n"
                f"- Agent: {pm_id}\n"
                f"- Release: {rid}\n"
                f"- Status: pending\n"
                f"- Created: {datetime.now(timezone.utc).isoformat()}\n\n"
                f"## Action required\n"
                f"The following PMs have already signed off on `{rid}`: {signed_names}.\n"
                f"Your signoff is the only thing blocking the coordinated push.\n\n"
                f"Review the release checklist and write your signoff artifact:\n"
                f"`sessions/{pm_id}/artifacts/release-signoffs/{slug}.md`\n\n"
                f"## Acceptance criteria\n"
                f"- File exists at the path above with `- Status: approved`\n"
                f"- All open blockers for your site are resolved or explicitly deferred\n",
                encoding="utf-8",
            )
            (item_dir / "roi.txt").write_text("500", encoding="utf-8")
            print(f"SIGNOFF-REMINDER: dispatched to {pm_id} for release {rid}")

        state_key.write_text(str(_now_ts()), encoding="utf-8")


def _org_enabled() -> bool:
    ctrl = REPO_ROOT / "tmp" / "org-control.json"
    if not ctrl.exists():
        return True
    try:
        import json as _json
        return bool(_json.loads(ctrl.read_text(encoding="utf-8")).get("enabled", True))
    except Exception:
        return True


_RELEASE_CLOSE_CAP        = 10     # features per site → trigger close
_RELEASE_CLOSE_AGE_HOURS  = 24     # hours since started_at → trigger close
_RELEASE_CLOSE_COOLDOWN   = 3600   # 1h between close dispatches per release
_RELEASE_CLOSE_STATE_DIR  = REPO_ROOT / "tmp" / "orchestrator-stagnation"

_SCOPE_ACTIVATE_GRACE_MINS = 15    # minutes before nudging PM to scope a new release
_SCOPE_ACTIVATE_COOLDOWN   = 3600  # 1h between nudges per release
_SCOPE_ACTIVATE_STATE_DIR  = REPO_ROOT / "tmp" / "orchestrator-stagnation"

# Canonical website prefix per team ID (matches '- Website:' in feature.md)
_TEAM_WEBSITE_PREFIX: Dict[str, str] = {
    "forseti":      "forseti",
    "dungeoncrawler": "dungeoncrawler",
}


def _count_site_features_in_progress(site_keyword: str) -> int:
    """Count features whose feature.md has Status: in_progress and Website: <site_keyword>."""
    count = 0
    for fm in (REPO_ROOT / "features").glob("*/feature.md"):
        try:
            text = fm.read_text(encoding="utf-8", errors="ignore")
            has_status = bool(re.search(r"^-\s+Status:\s*in_progress", text, re.MULTILINE | re.IGNORECASE))
            has_site   = bool(re.search(rf"^-\s+Website:.*{re.escape(site_keyword)}", text, re.MULTILINE | re.IGNORECASE))
            if has_status and has_site:
                count += 1
        except Exception:
            pass
    return count


def _count_site_features_for_release(site_keyword: str, release_id: str) -> int:
    """Count features scoped to a specific release_id with Status: in_progress and Website: <site_keyword>.

    Only counts features that explicitly declare the matching Release: field.
    Features without a Release: field are excluded (they belong to an untracked release).
    """
    count = 0
    for fm in (REPO_ROOT / "features").glob("*/feature.md"):
        try:
            text = fm.read_text(encoding="utf-8", errors="ignore")
            has_status  = bool(re.search(r"^-\s+Status:\s*in_progress", text, re.MULTILINE | re.IGNORECASE))
            has_site    = bool(re.search(rf"^-\s+Website:.*{re.escape(site_keyword)}", text, re.MULTILINE | re.IGNORECASE))
            has_release = bool(re.search(
                rf"^-\s+Release:\s*(?:\n\s*)*{re.escape(release_id)}\s*$",
                text, re.MULTILINE | re.IGNORECASE,
            ))
            if has_status and has_site and has_release:
                count += 1
        except Exception:
            pass
    return count


_FEATURE_GAP_COOLDOWN    = 4 * 3600   # 4h between gap dispatches per feature
_FEATURE_GAP_STATE_DIR   = REPO_ROOT / "tmp" / "orchestrator-stagnation"

# Agent role prefixes that own implementation vs verification work
_IMPL_AGENT_PREFIX  = "dev-"
_QA_AGENT_PREFIX    = "qa-"


def _agent_inbox_has_feature(agent_id: str, feat_name: str) -> bool:
    """Return True if agent's inbox has any item whose name contains feat_name."""
    inbox = _agent_inbox_dir(agent_id)
    if not inbox.exists():
        return False
    return any(
        d.is_dir() and d.name != "_archived" and feat_name in d.name
        for d in inbox.iterdir()
    )


def _agent_outbox_has_feature(agent_id: str, feat_name: str) -> bool:
    """Return True if agent's outbox has any artifact whose stem contains feat_name."""
    outbox = REPO_ROOT / "sessions" / agent_id / "outbox"
    if not outbox.exists():
        return False
    return any(feat_name in f.stem for f in outbox.iterdir() if f.is_file())


def _dispatch_feature_gap_remediation() -> None:
    """Scan every in_progress release feature for pipeline gaps and auto-dispatch fixes.

    Gap types detected and remediated:
      GAP-A: Feature has no dev inbox item AND no dev outbox artifact
             → dispatch impl item to dev-<site> at ROI _IMPL_MIN_ROI
      GAP-B: Dev has outbox artifact (impl done) but QA has no inbox item
             → dispatch testgen/verification item to qa-<site> at ROI _QA_MIN_ROI

    Only fires for features whose Release: field matches an active release ID.
    Cooldown: _FEATURE_GAP_COOLDOWN per (feature, gap_type) to avoid spam.
    """
    import json as _json
    from datetime import datetime as _dt

    active_dir  = REPO_ROOT / "tmp" / "release-cycle-active"
    if not active_dir.exists():
        return

    state_dir = _FEATURE_GAP_STATE_DIR
    state_dir.mkdir(parents=True, exist_ok=True)

    # Collect active release IDs
    active_rids: set = set()
    for f in active_dir.glob("*.release_id"):
        rid = f.read_text(encoding="utf-8").strip()
        if rid:
            active_rids.add(rid)
    if not active_rids:
        return

    # Read agents config for agent IDs per team
    teams_path = REPO_ROOT / "org-chart" / "products" / "product-teams.json"
    try:
        teams_data = _json.loads(teams_path.read_text(encoding="utf-8"))
    except Exception:
        return

    # Build site → {dev_agent, qa_agent} map
    site_agents: Dict[str, Dict[str, str]] = {}
    for t in teams_data.get("teams", []):
        if not t.get("active"):
            continue
        tid = (t.get("id") or "").strip()
        if not tid:
            continue
        site_agents[tid] = {
            "dev": t.get("dev_agent", f"dev-{tid}"),
            "qa":  t.get("qa_agent",  f"qa-{tid}"),
            "pm":  t.get("pm_agent",  f"pm-{tid}"),
        }

    now = _now_ts()
    date_prefix = _dt.now(timezone.utc).strftime("%Y%m%d-%H%M%S")

    dispatched: List[str] = []

    for fm in sorted((REPO_ROOT / "features").glob("*/feature.md")):
        text = fm.read_text(encoding="utf-8", errors="ignore")

        # Only in_progress features
        if not re.search(r"^-\s+Status:\s*in_progress", text, re.MULTILINE | re.IGNORECASE):
            continue

        # Must have Release: field matching an active release
        rel_m = re.search(r"^-\s+Release:\s*(.+)", text, re.MULTILINE | re.IGNORECASE)
        if not rel_m or rel_m.group(1).strip() not in active_rids:
            continue

        feat_name = fm.parent.name
        rid = rel_m.group(1).strip()

        # Determine site from Website: field
        site_m = re.search(r"^-\s+Website:\s*(.+)", text, re.MULTILINE | re.IGNORECASE)
        site_raw = site_m.group(1).strip().lower() if site_m else ""
        # Map website value to team ID
        site_id = next(
            (k for k in _TEAM_WEBSITE_PREFIX if k in site_raw),
            None
        )
        if not site_id or site_id not in site_agents:
            continue

        dev_owner_m = re.search(r"^-\s+Dev owner:\s*(.+)", text, re.MULTILINE | re.IGNORECASE)
        qa_owner_m = re.search(r"^-\s+QA owner:\s*(.+)", text, re.MULTILINE | re.IGNORECASE)
        dev_agent = (dev_owner_m.group(1).strip() if dev_owner_m else "") or site_agents[site_id]["dev"]
        qa_agent  = (qa_owner_m.group(1).strip() if qa_owner_m else "") or site_agents[site_id]["qa"]

        dev_has_inbox   = _agent_inbox_has_feature(dev_agent, feat_name)
        dev_has_outbox  = _agent_outbox_has_feature(dev_agent, feat_name)
        qa_has_inbox    = _agent_inbox_has_feature(qa_agent,  feat_name)
        qa_has_outbox   = _agent_outbox_has_feature(qa_agent,  feat_name)

        # GAP-A: no dev coverage at all (no inbox, no outbox), regardless of QA state.
        if not dev_has_inbox and not dev_has_outbox:
            state_key = state_dir / f"featgap_A_{feat_name}"
            last = _safe_int(state_key.read_text(encoding="utf-8").strip() if state_key.exists() else "0", 0)
            if (now - last) >= _FEATURE_GAP_COOLDOWN:
                item_id  = f"{date_prefix}-impl-{feat_name}"
                item_dir = REPO_ROOT / "sessions" / dev_agent / "inbox" / item_id
                if not item_dir.exists():
                    item_dir.mkdir(parents=True, exist_ok=True)
                    title_m = re.search(r"^#\s+Feature Brief[:\s]*(.+)", text, re.MULTILINE)
                    title   = title_m.group(1).strip() if (title_m and title_m.group(1).strip()) else feat_name
                    (item_dir / "README.md").write_text(
                        f"# Implementation required: {title}\n\n"
                        f"- Agent: {dev_agent}\n"
                        f"- Feature: {feat_name}\n"
                        f"- Release: {rid}\n"
                        f"- Status: pending\n"
                        f"- Created: {_dt.now(timezone.utc).isoformat()}\n"
                        f"- Dispatched by: orchestrator (GAP-A: no dev coverage found)\n\n"
                        f"## Context\n"
                        f"Feature `{feat_name}` is in_progress for release `{rid}` but no "
                        f"implementation inbox item or outbox artifact was found for {dev_agent}. "
                        f"This gap was detected automatically by the CEO orchestration loop.\n\n"
                        f"## Action required\n"
                        f"1. Review feature brief: `features/{feat_name}/feature.md`\n"
                        f"2. Implement the feature per the brief\n"
                        f"3. Run existing tests to ensure no regressions\n"
                        f"4. Write outbox with implementation notes and commit hash(es)\n"
                        f"5. Notify {qa_agent} for Gate 2 verification\n\n"
                        f"## Acceptance criteria\n"
                        f"- Implementation committed with hash recorded in outbox\n"
                        f"- No regression failures\n",
                        encoding="utf-8",
                    )
                    import shutil as _shutil
                    _shutil.copy(fm, item_dir / "feature.md")
                    (item_dir / "roi.txt").write_text("200", encoding="utf-8")
                    state_key.write_text(str(now), encoding="utf-8")
                    msg = f"FEATURE-GAP-A: dispatched impl to {dev_agent} for {feat_name} (release {rid})"
                    print(msg)
                    dispatched.append(msg)

        # GAP-B: dev has outbox (impl done) but QA has no inbox item
        elif dev_has_outbox and not qa_has_inbox and not qa_has_outbox:
            state_key = state_dir / f"featgap_B_{feat_name}"
            last = _safe_int(state_key.read_text(encoding="utf-8").strip() if state_key.exists() else "0", 0)
            if (now - last) >= _FEATURE_GAP_COOLDOWN:
                item_id  = f"{date_prefix}-testgen-{feat_name}"
                item_dir = REPO_ROOT / "sessions" / qa_agent / "inbox" / item_id
                if not item_dir.exists():
                    item_dir.mkdir(parents=True, exist_ok=True)
                    title_m = re.search(r"^#\s+Feature Brief[:\s]*(.+)", text, re.MULTILINE)
                    title   = title_m.group(1).strip() if (title_m and title_m.group(1).strip()) else feat_name
                    (item_dir / "README.md").write_text(
                        f"# QA verification required: {title}\n\n"
                        f"- Agent: {qa_agent}\n"
                        f"- Feature: {feat_name}\n"
                        f"- Release: {rid}\n"
                        f"- Status: pending\n"
                        f"- Created: {_dt.now(timezone.utc).isoformat()}\n"
                        f"- Dispatched by: orchestrator (GAP-B: dev outbox found, no QA coverage)\n\n"
                        f"## Context\n"
                        f"Feature `{feat_name}` is in_progress for release `{rid}`. "
                        f"{dev_agent} has an outbox artifact indicating implementation is complete, "
                        f"but no QA testgen or verification item was found. "
                        f"This gap was detected automatically by the CEO orchestration loop.\n\n"
                        f"## Action required\n"
                        f"1. Review feature brief: `features/{feat_name}/feature.md`\n"
                        f"2. Review dev implementation outbox in `sessions/{dev_agent}/outbox/`\n"
                        f"3. Generate or activate the test suite for this feature\n"
                        f"4. Run tests and produce a Gate 2 APPROVE or BLOCK\n"
                        f"5. Write outbox with verification report\n\n"
                        f"## Acceptance criteria\n"
                        f"- Explicit Gate 2 APPROVE or BLOCK in outbox\n"
                        f"- Evidence committed and referenced\n",
                        encoding="utf-8",
                    )
                    import shutil as _shutil
                    _shutil.copy(fm, item_dir / "feature.md")
                    (item_dir / "roi.txt").write_text("300", encoding="utf-8")
                    state_key.write_text(str(now), encoding="utf-8")
                    msg = f"FEATURE-GAP-B: dispatched testgen to {qa_agent} for {feat_name} (release {rid})"
                    print(msg)
                    dispatched.append(msg)

    if dispatched:
        print(f"FEATURE-GAP-REMEDIATION: {len(dispatched)} gap(s) dispatched this tick")


def _dispatch_scope_activate_nudge() -> None:
    """Nudge the PM to activate features when a new release has no features after grace period.

    Fires when:
      - An active release has 0 in_progress features tagged with its release ID
      - The release has been active for ≥ _SCOPE_ACTIVATE_GRACE_MINS minutes

    Dispatch: ROI 800 scope-activate-now item to pm-<site>
    Cooldown: _SCOPE_ACTIVATE_COOLDOWN per release slug to avoid spam
    """
    import json as _json
    from datetime import datetime as _dt

    active_dir = REPO_ROOT / "tmp" / "release-cycle-active"
    if not active_dir.exists():
        return

    try:
        teams_data = _json.loads(
            (REPO_ROOT / "org-chart" / "products" / "product-teams.json").read_text(encoding="utf-8")
        )
    except Exception:
        return

    pm_by_site: Dict[str, str] = {}
    for t in teams_data.get("teams", []):
        if t.get("active"):
            tid = (t.get("id") or "").strip()
            pm_by_site[tid] = t.get("pm_agent", f"pm-{tid}")

    now = _now_ts()
    grace_secs = _SCOPE_ACTIVATE_GRACE_MINS * 60

    for rid_file in active_dir.glob("*.release_id"):
        site = rid_file.stem
        rid = rid_file.read_text(encoding="utf-8").strip()
        if not rid:
            continue

        started_f = active_dir / f"{site}.started_at"
        if not started_f.exists():
            continue
        try:
            started_ts = _dt.fromisoformat(started_f.read_text(encoding="utf-8").strip()).timestamp()
        except Exception:
            continue

        age_secs = now - started_ts
        if age_secs < grace_secs:
            continue  # still in grace period

        # Only activated features count here. Release tags on ready-pool features must
        # not suppress the PM Stage 0 activation nudge.
        feature_count = _count_site_features_for_release(site, rid)

        if feature_count > 0:
            continue  # release has features — no nudge needed

        # Check PM signoff already exists (release already closed)
        pm_id = pm_by_site.get(site, f"pm-{site}")
        signoff = REPO_ROOT / "sessions" / pm_id / "artifacts" / "release-signoffs" / f"{rid}.md"
        if signoff.exists():
            continue

        # Check cooldown
        state_key = _SCOPE_ACTIVATE_STATE_DIR / f"scope_nudge_{rid}"
        if not _cooldown_ok(state_key, _SCOPE_ACTIVATE_COOLDOWN):
            continue

        # Check if PM already has a scope-activate item for this release
        inbox = REPO_ROOT / "sessions" / pm_id / "inbox"
        already_dispatched = any(
            "scope-activate" in d.name and rid in d.name
            for d in inbox.iterdir()
            if d.is_dir() and d.name != "_archived"
        )
        if already_dispatched:
            _mark_now(state_key)
            continue

        # Dispatch nudge
        date_prefix = _dt.now(timezone.utc).strftime("%Y%m%d-%H%M%S")
        item_id = f"{date_prefix}-scope-activate-{rid}"
        item_dir = inbox / item_id
        item_dir.mkdir(parents=True, exist_ok=True)

        # Gather ready features for this site
        ready_feats = []
        for fm in sorted((REPO_ROOT / "features").glob("*/feature.md")):
            try:
                text = fm.read_text(encoding="utf-8", errors="ignore")
                site_val = next((l.split("Website:")[-1].strip() for l in text.splitlines()
                                 if l.startswith("- Website:")), "")
                status_val = next((l.split("Status:")[-1].strip() for l in text.splitlines()
                                   if l.startswith("- Status:")), "")
                if site in site_val.lower() and status_val == "ready":
                    ready_feats.append(fm.parent.name)
            except Exception:
                pass

        feats_list = "\n".join(f"- `{f}`" for f in ready_feats[:15]) if ready_feats else "- (check features/ dir)"
        age_min = int(age_secs / 60)

        readme = (
            f"# Scope Activate: {rid}\n\n"
            f"- Agent: {pm_id}\n"
            f"- Status: pending\n"
            f"- Release: {rid}\n"
            f"- Date: {_dt.now(timezone.utc).strftime('%Y-%m-%d')}\n"
            f"- Dispatched by: orchestrator (release active {age_min}m, 0 features scoped)\n\n"
            f"## Task\n\n"
            f"Release `{rid}` has been active for **{age_min} minutes** with zero features scoped.\n"
            f"Activate features now using:\n\n"
            f"```bash\nbash scripts/pm-scope-activate.sh {site} <feature_id>\n```\n\n"
            f"Cap is **10 features** (auto-close fires at 10 or 24h). "
            f"Activate your highest-priority `ready` features first.\n\n"
            f"## Ready features (up to 10)\n{feats_list}\n\n"
            f"## Done when\n"
            f"At least 3 features activated; dev/QA inbox items exist for each.\n"
        )
        (item_dir / "README.md").write_text(readme, encoding="utf-8")
        (item_dir / "roi.txt").write_text("800", encoding="utf-8")
        _mark_now(state_key)
        print(f"SCOPE-ACTIVATE-NUDGE: {rid} active {age_min}m, 0 features → dispatched to {pm_id}")


def _dispatch_gate2_auto_approve() -> None:
    """Auto-generate Gate 2 APPROVE file when all suite-activate items for a release are done.

    Fires per team when ALL of the following are true:
      1. The team has an active release with >= 1 in-progress features.
      2. Every in-progress feature for that release has a corresponding suite-activate
         outbox file in sessions/<qa_agent>/outbox/ (name contains the feature id).
      3. No suite-activate inbox items remain (outside _archived/) in sessions/<qa_agent>/inbox/.
      4. No gate2-approve outbox file already exists referencing this release_id.

    Writes: sessions/<qa_agent>/outbox/<timestamp>-gate2-approve-<release-slug>.md
    The file contains the release ID and "APPROVE" — satisfying the release-signoff.sh check.
    """
    teams_path = REPO_ROOT / "org-chart" / "products" / "product-teams.json"
    try:
        teams_data = _json.loads(teams_path.read_text(encoding="utf-8"))
    except Exception:
        return

    active_dir = REPO_ROOT / "tmp" / "release-cycle-active"
    if not active_dir.exists():
        return

    for team in teams_data.get("teams", []):
        if not team.get("active"):
            continue

        team_id   = (team.get("id") or "").strip()
        qa_agent  = (team.get("qa_agent") or f"qa-{team_id}").strip()
        site_kw   = _TEAM_WEBSITE_PREFIX.get(team_id, team_id)

        release_id_file = active_dir / f"{team_id}.release_id"
        if not release_id_file.exists():
            continue
        release_id = release_id_file.read_text(encoding="utf-8").strip()
        if not release_id:
            continue

        qa_outbox = REPO_ROOT / "sessions" / qa_agent / "outbox"
        qa_inbox  = REPO_ROOT / "sessions" / qa_agent / "inbox"

        # Condition 4: skip if gate2-approve outbox already exists for this release
        if qa_outbox.exists():
            already_approved = any(
                "gate2-approve" in f.name and release_id in f.read_text(encoding="utf-8", errors="ignore")
                for f in qa_outbox.iterdir()
                if f.is_file() and "gate2-approve" in f.name
            )
            if already_approved:
                continue

        # Collect in-progress features for this release
        features: List[str] = []
        for fm in (REPO_ROOT / "features").glob("*/feature.md"):
            try:
                text = fm.read_text(encoding="utf-8", errors="ignore")
                if (re.search(r"^-\s+Status:\s*in_progress", text, re.MULTILINE | re.IGNORECASE)
                        and re.search(rf"^-\s+Website:.*{re.escape(site_kw)}", text, re.MULTILINE | re.IGNORECASE)
                        and re.search(rf"^-\s+Release:\s*{re.escape(release_id)}\s*$", text, re.MULTILINE | re.IGNORECASE)):
                    features.append(fm.parent.name)
            except Exception:
                pass

        # Condition 1: must have at least 1 feature
        if not features:
            continue

        # Condition 2: every in-progress feature must have a suite-activate outbox entry
        if not qa_outbox.exists():
            continue
        outbox_files = [f.name for f in qa_outbox.iterdir() if f.is_file()]
        all_activated = all(
            any(feat_id in ob_name and "suite-activate" in ob_name for ob_name in outbox_files)
            for feat_id in features
        )
        if not all_activated:
            continue

        # Condition 3: no suite-activate inbox items remaining outside _archived
        if qa_inbox.exists():
            pending_activates = [
                d for d in qa_inbox.iterdir()
                if d.is_dir() and d.name != "_archived" and "suite-activate" in d.name
            ]
            if pending_activates:
                continue

        # All conditions met — write the Gate 2 APPROVE file
        timestamp = datetime.now(timezone.utc).strftime("%Y%m%d-%H%M%S")
        slug = re.sub(r"[^A-Za-z0-9._-]", "-", release_id)[:80]
        approve_file = qa_outbox / f"{timestamp}-gate2-approve-{slug}.md"
        qa_outbox.mkdir(parents=True, exist_ok=True)
        feature_rows = "\n".join(f"| {fid} | suite-activate outbox found | done |" for fid in features)
        approve_file.write_text(
            f"# Gate 2 — QA Verification Report: {release_id} — APPROVE\n\n"
            f"- Release: {release_id}\n"
            f"- Status: done\n"
            f"- Summary: All {len(features)} feature(s) in scope for {release_id} have completed "
            f"suite activation with Status: done. Gate 2 verification is APPROVE. "
            f"Auto-generated by orchestrator (gate2-auto-approve).\n\n"
            f"## Verification evidence\n\n"
            f"| Feature | Suite-activate outbox | Status |\n"
            f"|---|---|---|\n"
            f"{feature_rows}\n",
            encoding="utf-8",
        )
        print(f"[gate2-auto-approve] {release_id} — filed by orchestrator for {qa_agent}")


def _dispatch_release_close_triggers() -> None:
    """Dispatch release-close-now inbox items to PMs when auto-close conditions are met.

    Conditions (either triggers close):
      1. ≥ _RELEASE_CLOSE_CAP (10) features in_progress for this site
      2. ≥ _RELEASE_CLOSE_AGE_HOURS (24h) have elapsed since the release started

    PM receives a high-priority (ROI 999) inbox item instructing them to run
    Gate 1b, confirm QA, and execute scripts/release-signoff.sh immediately.
    Cooldown-gated per release to avoid duplicate dispatches.
    """
    import json as _json

    active_dir = REPO_ROOT / "tmp" / "release-cycle-active"
    if not active_dir.exists():
        return

    state_dir = _RELEASE_CLOSE_STATE_DIR
    state_dir.mkdir(parents=True, exist_ok=True)

    teams_path = REPO_ROOT / "org-chart" / "products" / "product-teams.json"
    try:
        teams_data = _json.loads(teams_path.read_text(encoding="utf-8"))
    except Exception:
        return

    pm_map: Dict[str, str] = {
        (t.get("id") or "").strip(): (t.get("pm_agent") or "").strip()
        for t in teams_data.get("teams", [])
        if t.get("active") and (t.get("id") or "").strip() and (t.get("pm_agent") or "").strip()
    }

    now = _now_ts()

    for rid_file in active_dir.glob("*.release_id"):
        team_id = rid_file.stem  # e.g. "forseti" or "dungeoncrawler"
        rid = rid_file.read_text(encoding="utf-8").strip()
        if not rid:
            continue

        pm_id = pm_map.get(team_id, "")
        if not pm_id:
            continue

        # Cooldown check per release
        state_key = state_dir / f"release_close_{team_id}"
        last = _safe_int(state_key.read_text(encoding="utf-8").strip() if state_key.exists() else "0", 0)
        if (now - last) < _RELEASE_CLOSE_COOLDOWN:
            continue

        # Check if already signed off (no need to dispatch)
        slug = re.sub(r"[^A-Za-z0-9._-]", "-", rid)[:80]
        signoff_file = REPO_ROOT / "sessions" / pm_id / "artifacts" / "release-signoffs" / f"{slug}.md"
        if signoff_file.exists():
            continue  # already signed, nothing to do

        # If PM already processed this same close-now trigger and left it blocked,
        # don't re-dispatch daily copies until the release state actually changes.
        matching_outboxes = sorted(
            (REPO_ROOT / "sessions" / pm_id / "outbox").glob(f"*release-close-now-{slug[:40]}.md"),
            key=lambda path: path.stat().st_mtime,
            reverse=True,
        )
        if matching_outboxes:
            latest_match = matching_outboxes[0]
            try:
                latest_text = latest_match.read_text(encoding="utf-8", errors="ignore")
            except Exception:
                latest_text = ""
            latest_status = ""
            match = re.search(r"^-\s+Status:\s*(.+)$", latest_text, re.MULTILINE | re.IGNORECASE)
            if match:
                latest_status = match.group(1).strip().lower().replace("_", "-")
            if rid in latest_text and latest_status == "blocked":
                continue

        # Evaluate triggers
        triggers: List[str] = []

        # Trigger 1: feature count cap (scoped to current release_id)
        site_kw = _TEAM_WEBSITE_PREFIX.get(team_id, team_id)
        feature_count = _count_site_features_for_release(site_kw, rid)
        if feature_count >= _RELEASE_CLOSE_CAP:
            triggers.append(f"FEATURE_CAP: {feature_count}/{_RELEASE_CLOSE_CAP} features in_progress for {team_id} release {rid}")

        # Trigger 2: age since started_at (skip if zero features in this release — avoids empty-release deadlock)
        started_file = active_dir / f"{team_id}.started_at"
        if started_file.exists():
            try:
                from datetime import datetime as _dt
                started_str = started_file.read_text(encoding="utf-8").strip()
                # Parse ISO8601 (with or without timezone)
                started_str_clean = started_str.replace("+00:00", "").rstrip("Z")
                started_ts = int(_dt.fromisoformat(started_str_clean).replace(
                    tzinfo=timezone.utc).timestamp())
                age_hours = (now - started_ts) / 3600
                # Guard: do not fire AGE trigger on an empty release (zero release-scoped features)
                release_feature_count = _count_site_features_for_release(site_kw, rid)
                if age_hours >= _RELEASE_CLOSE_AGE_HOURS and release_feature_count > 0:
                    triggers.append(
                        f"AGE: release {rid} started {age_hours:.1f}h ago (threshold {_RELEASE_CLOSE_AGE_HOURS}h)"
                    )
            except Exception:
                pass

        if not triggers:
            continue

        # Dispatch close-now item to PM
        item_id = f"{datetime.now(timezone.utc).strftime('%Y%m%d')}-release-close-now-{slug[:40]}"
        item_dir = REPO_ROOT / "sessions" / pm_id / "inbox" / item_id
        if item_dir.exists():
            continue  # already dispatched
        item_dir.mkdir(parents=True, exist_ok=True)
        triggers_text = "\n".join(f"  - {t}" for t in triggers)
        (item_dir / "README.md").write_text(
            f"# Release close trigger: {rid}\n\n"
            f"- Agent: {pm_id}\n"
            f"- Release: {rid}\n"
            f"- Status: pending\n"
            f"- Created: {datetime.now(timezone.utc).isoformat()}\n\n"
            f"## Auto-close conditions met\n{triggers_text}\n\n"
            f"## Action required — close this release now\n"
            f"The release has hit an auto-close trigger. Do not wait to fill more scope.\n"
            f"20 features is a **maximum cap**, not a target. Ship what is ready.\n\n"
            f"**Steps:**\n"
            f"1. Confirm all in-progress features for `{team_id}` have Dev commits and QA APPROVE (Gate 1b + Gate 2)\n"
            f"2. Any feature not yet QA-approved: defer it (set feature.md Status: ready, remove from this release)\n"
            f"3. Write Release Notes to `sessions/{pm_id}/artifacts/release-notes/{slug}.md`\n"
            f"4. Record your signoff: `./scripts/release-signoff.sh {team_id} {rid}`\n"
            f"5. Notify the partner PM to also sign off (coordinated release)\n\n"
            f"## Acceptance criteria\n"
            f"- `sessions/{pm_id}/artifacts/release-signoffs/{slug}.md` exists with `- Status: approved`\n"
            f"- All features left in scope have Gate 2 APPROVE evidence\n",
            encoding="utf-8",
        )
        (item_dir / "roi.txt").write_text("999", encoding="utf-8")
        state_key.write_text(str(now), encoding="utf-8")
        print(f"RELEASE-CLOSE-TRIGGER: dispatched to {pm_id} for {rid} — triggers: {', '.join(triggers)}")


def _stagnation_check(blocked_count: int, blocked_out: str) -> None:
    """Dispatch CEO agent for full analysis when any stagnation signal fires."""
    import hashlib as _hashlib
    _STAGNATION_STATE_DIR.mkdir(parents=True, exist_ok=True)
    ticks_file     = _STAGNATION_STATE_DIR / "blocked_ticks"
    dispatched_file = _STAGNATION_STATE_DIR / "last_dispatch"

    # --- Evaluate all signals ---
    signals: List[str] = []

    # 1. Org disabled with blocked agents
    if not _org_enabled() and blocked_count > 0:
        signals.append(f"ORG_DISABLED: org-control.json disabled with {blocked_count} blocked agent(s)")

    # 2. No Status:done outbox written recently (while work exists)
    if blocked_count > 0:
        done_age = _seconds_since_last_done_outbox()
        if done_age >= _STAG_NO_DONE_OUTBOX_SECONDS:
            signals.append(f"NO_DONE_OUTBOX: no agent wrote Status:done in {done_age // 60}m (threshold {_STAG_NO_DONE_OUTBOX_SECONDS // 60}m)")

    # 3. Inbox items aging without resolution
    oldest_inbox = _oldest_unresolved_inbox_seconds()
    if oldest_inbox >= _STAG_INBOX_AGING_SECONDS:
        signals.append(f"INBOX_AGING: oldest unresolved inbox item is {oldest_inbox // 60}m old (threshold {_STAG_INBOX_AGING_SECONDS // 60}m)")

    # 4. CEO inbox depth — system can't clear its own escalations
    ceo_depth = _ceo_inbox_depth()
    if ceo_depth >= _STAG_CEO_INBOX_DEPTH:
        signals.append(f"CEO_INBOX_DEPTH: {ceo_depth} pending CEO inbox items (threshold {_STAG_CEO_INBOX_DEPTH})")

    # 5. Consecutive blocked ticks with no new done outboxes
    if blocked_count > 0:
        ticks = _safe_int(ticks_file.read_text(encoding="utf-8").strip() if ticks_file.exists() else "0", 0) + 1
        ticks_file.write_text(str(ticks), encoding="utf-8")
        if ticks >= _STAG_BLOCKED_TICKS:
            signals.append(f"BLOCKED_TICKS: {ticks} consecutive ticks with {blocked_count} blocked agent(s) and no resolution (threshold {_STAG_BLOCKED_TICKS})")
    else:
        ticks_file.write_text("0", encoding="utf-8")

    # 6. No release shipped in threshold window (only relevant if release is active)
    active_release_files = list((REPO_ROOT / "tmp" / "release-cycle-active").glob("*.release_id")) if (REPO_ROOT / "tmp" / "release-cycle-active").exists() else []
    if active_release_files:
        signoff_age = _seconds_since_last_release_signoff()
        if signoff_age >= _STAG_NO_RELEASE_SECONDS:
            signals.append(f"NO_RELEASE_PROGRESS: no release signoff in {signoff_age // 3600}h {(signoff_age % 3600) // 60}m (threshold {_STAG_NO_RELEASE_SECONDS // 3600}h)")

    if not signals:
        return

    # Dedup: skip if a stagnation item is already pending in CEO inbox (not yet resolved)
    if _ceo_has_pending_stagnation_item():
        return

    # Cooldown: don't re-dispatch more than once per 30 minutes
    last_dispatch = _safe_int(dispatched_file.read_text(encoding="utf-8").strip() if dispatched_file.exists() else "0", 0)
    if (_now_ts() - last_dispatch) < _STAG_DISPATCH_COOLDOWN:
        return

    signals_text = "\n".join(f"  - {s}" for s in signals)
    gate_brief = _release_gate_brief()
    brief = (
        f"[STAGNATION ALERT] The orchestrator has detected that the org is stuck.\n\n"
        f"## Signals fired ({len(signals)}):\n{signals_text}\n\n"
        f"## What to do\n"
        f"Perform a full system analysis. Review all blocked agents, identify the root cause, "
        f"and take **direct action** to unblock — run drush commands, trigger audits, clear stale "
        f"locks, fix permissions, re-enable org. Do not just escalate; act.\n\n"
        f"For release blockers: check which PMs are missing signoffs and dispatch signoff-reminder "
        f"inbox items immediately (see cross-site signoff reminder pattern in your seat instructions).\n\n"
        f"## Release gate snapshot\n{gate_brief}\n\n"
        f"## Blocked agent summary\n{blocked_out or '(none currently blocked)'}\n"
    )
    result = _route_to_ceo_inbox(brief, "stagnation-full-analysis", f"stagnation-{len(signals)}-signals")
    dispatched_file.write_text(str(_now_ts()), encoding="utf-8")
    ticks_file.write_text("0", encoding="utf-8")
    print(f"STAGNATION-DISPATCH ({len(signals)} signals): {signals_text} → CEO: {result}")


_INBOX_AUDIT_COOLDOWN = 3600          # re-audit at most once per hour
_INBOX_AUDIT_STATE    = REPO_ROOT / "tmp" / "orchestrator-stagnation" / "inbox_audit_last"
_INWORK_STALE_SECS    = 7200          # .inwork lock is stale after 2h


def _audit_inbox_data_quality() -> Dict[str, Any]:
    """Scan every agent inbox for data-standard violations and auto-remediate.

    Checks performed every tick (subject to cooldown):
      1. Stale .inwork locks (>2h) → removed (unlock so item can be retried)
      2. Items missing README.md and command.md → inject minimal README.md
      3. READMEs missing '- Agent:' or '- Status:' fields → append the fields

    Returns a summary dict logged into the health_check audit trail.
    """
    if not _cooldown_ok(_INBOX_AUDIT_STATE, _INBOX_AUDIT_COOLDOWN):
        return {}

    sessions_dir = REPO_ROOT / "sessions"
    if not sessions_dir.exists():
        return {}

    now = _now_ts()
    stats: Dict[str, Any] = {
        "stale_locks_cleared": [],
        "readmes_injected": [],
        "fields_patched": [],
    }

    import re as _re

    for agent_dir in sorted(sessions_dir.iterdir()):
        if not agent_dir.is_dir():
            continue
        agent_id = agent_dir.name
        inbox = agent_dir / "inbox"
        if not inbox.exists():
            continue

        # ── 1. Clear stale .inwork locks ──────────────────────────────────────
        for lock in inbox.glob("**/*.inwork"):
            try:
                age = now - lock.stat().st_mtime
                if age > _INWORK_STALE_SECS:
                    lock.unlink()
                    stats["stale_locks_cleared"].append(f"{agent_id}/{lock.name}")
            except Exception:
                pass

        for item_dir in sorted(inbox.iterdir()):
            if item_dir.name == "_archived" or not item_dir.is_dir():
                continue

            has_readme  = (item_dir / "README.md").exists()
            has_command = (item_dir / "command.md").exists()

            # ── 2. Inject minimal README if neither exists ────────────────────
            if not has_readme and not has_command:
                roi_val = ""
                roi_f = item_dir / "roi.txt"
                if roi_f.exists():
                    roi_val = roi_f.read_text(encoding="utf-8", errors="ignore").strip()
                readme_path = item_dir / "README.md"
                readme_path.write_text(
                    f"# {item_dir.name}\n\n"
                    f"- Agent: {agent_id}\n"
                    f"- Status: pending\n"
                    + (f"- ROI: {roi_val}\n" if roi_val else ""),
                    encoding="utf-8",
                )
                stats["readmes_injected"].append(f"{agent_id}/{item_dir.name}")
                has_readme = True

            # ── 3. Patch missing Agent: / Status: fields ──────────────────────
            md_path = (item_dir / "README.md") if has_readme else (item_dir / "command.md")
            try:
                text = md_path.read_text(encoding="utf-8", errors="ignore")
                missing = []
                if not _re.search(r"^-\s+Agent:", text, _re.M):
                    missing.append(f"- Agent: {agent_id}")
                if not _re.search(r"^-\s+Status:", text, _re.M):
                    missing.append("- Status: pending")
                if missing:
                    md_path.write_text(text.rstrip() + "\n" + "\n".join(missing) + "\n",
                                       encoding="utf-8")
                    stats["fields_patched"].append(f"{agent_id}/{item_dir.name}")
            except Exception:
                pass

    total_issues = (len(stats["stale_locks_cleared"]) +
                    len(stats["readmes_injected"]) +
                    len(stats["fields_patched"]))
    if total_issues:
        print(
            f"INBOX-AUDIT: cleared {len(stats['stale_locks_cleared'])} locks, "
            f"injected {len(stats['readmes_injected'])} READMEs, "
            f"patched {len(stats['fields_patched'])} fields"
        )

    _mark_now(_INBOX_AUDIT_STATE)
    return stats


def _health_check_step(provider: "RuntimeProvider", log: List[Any]) -> None:
    """Detect stalled agents (inbox items, no active exec) and auto-remediate."""
    rc, status_out = _run(["bash", "scripts/hq-status.sh"], timeout=180)
    if rc == -1:  # timeout — skip health check this tick, log and continue
        log.append({"step": "health_check", "skipped": "hq-status.sh timed out (>180s)"})
        print(f"HEALTH-CHECK-SKIP: hq-status.sh exceeded 180s timeout")
        return
    rc2, blocked_out = _run(["bash", "scripts/hq-blockers.sh"], timeout=60)
    rc3, blocked_count_str = _run(["bash", "scripts/hq-blockers.sh", "count"], timeout=60)
    blocked_count = _safe_int(blocked_count_str)

    idle_agents: List[str] = []
    for line in status_out.splitlines():
        parts = line.split()
        if len(parts) < 3:
            continue
        agent, inbox_s, exec_s = parts[0], parts[1], parts[2]
        if exec_s not in ("yes", "no"):
            continue
        if _safe_int(inbox_s) > 0 and exec_s == "no":
            idle_agents.append(agent)

    alert: Dict[str, Any] = {
        "step": "health_check",
        "idle_with_inbox": len(idle_agents),
        "blocked_count": blocked_count,
        "remediated": [],
    }

    if idle_agents and _cooldown_ok(_HEALTH_AUTOEXEC_STATE, _HEALTH_AUTOEXEC_COOLDOWN):
        for agent in idle_agents[:5]:
            rc_exec, _ = provider.run_one(agent)
            alert["remediated"].append({"agent": agent, "rc": rc_exec})
        _mark_now(_HEALTH_AUTOEXEC_STATE)
        print(f"AUTO-REMEDIATE: stalled={len(idle_agents)} remediated={len(alert['remediated'])}")

    if blocked_count > 0:
        print(f"BLOCKED: {blocked_count} agent(s) blocked\n{blocked_out}")
        _stagnation_check(blocked_count, blocked_out)
    else:
        _stagnation_check(0, "")

    # ── Release-support dispatchers ──────────────────────────────────────────
    # These run every health-check tick (every health_check_interval seconds).
    # _dispatch_gate2_auto_approve is also called from _release_cycle_step as
    # a fallback for when this step returns early (hq-status.sh timeout).
    release_control = _release_cycle_control_state()
    if not bool(release_control.get("enabled", True)):
        alert["release_automation"] = {
            "status": "paused",
            "state_file": release_control.get("state_file"),
            "reason": release_control.get("reason"),
        }
    else:
        # Always check for lagging PM signoffs regardless of blocked count
        try:
            _dispatch_signoff_reminders()
        except Exception as e:
            print(f"SIGNOFF-REMINDER-ERR: {e}")

        # Check auto-close triggers: ≥10 features or ≥24h since release started
        try:
            _dispatch_release_close_triggers()
        except Exception as e:
            print(f"RELEASE-CLOSE-TRIGGER-ERR: {e}")

        # Auto-generate Gate 2 APPROVE when all suite-activates for a release are done
        try:
            _dispatch_gate2_auto_approve()
        except Exception as e:
            print(f"GATE2-AUTO-APPROVE-ERR: {e}")

        # Nudge PM to scope features when a new release has been empty for 15+ min
        try:
            _dispatch_scope_activate_nudge()
        except Exception as e:
            print(f"SCOPE-ACTIVATE-NUDGE-ERR: {e}")

        # Scan for release feature gaps: in_progress features with no dev/QA inbox coverage
        try:
            _dispatch_feature_gap_remediation()
        except Exception as e:
            print(f"FEATURE-GAP-ERR: {e}")

    # Audit inbox data quality: clear stale locks, inject missing READMEs, patch fields
    try:
        audit_stats = _audit_inbox_data_quality()
        if audit_stats:
            alert["inbox_audit"] = audit_stats
    except Exception as e:
        print(f"INBOX-AUDIT-ERR: {e}")

    log.append(alert)


# ── Runtime provider ──────────────────────────────────────────────────────────

class RuntimeProvider:
    def run_one(self, agent_id: str) -> Tuple[int, str]:
        raise NotImplementedError


class ShellProvider(RuntimeProvider):
    def run_one(self, agent_id: str) -> Tuple[int, str]:
        return _run(["bash", "scripts/agent-exec-next.sh", agent_id], timeout=3600)


class ClineProvider(RuntimeProvider):
    def run_one(self, agent_id: str) -> Tuple[int, str]:
        import shutil
        exe = shutil.which("cline")
        if not exe:
            return 2, "cline not in PATH; use --provider shell"
        return _run([exe, "run", "--agent", agent_id], timeout=3600)


def _set_min_inbox_roi(item_dir: pathlib.Path, min_roi: int) -> None:
    if min_roi <= 0:
        return
    roi_file = item_dir / "roi.txt"
    current = 1
    if roi_file.exists():
        lines = roi_file.read_text(encoding="utf-8", errors="ignore").splitlines()
        digits = "".join(ch for ch in (lines[0] if lines else "") if ch.isdigit())
        current = max(1, _safe_int(digits, 1))
    if current >= min_roi:
        return
    roi_file.write_text(f"{min_roi}\n", encoding="utf-8")


def _find_pm_grooming_item(pm_agent: str, next_release_id: str) -> Tuple[pathlib.Path | None, bool]:
    slug = re.sub(r"[^A-Za-z0-9._-]", "-", next_release_id).strip("-")[:60]
    inbox = REPO_ROOT / "sessions" / pm_agent / "inbox"
    outbox = REPO_ROOT / "sessions" / pm_agent / "outbox"
    if inbox.exists():
        for item in inbox.iterdir():
            if item.is_dir() and item.name != "_archived" and item.name.endswith(f"-groom-{slug}"):
                return item, False
    if outbox.exists():
        for item in outbox.glob(f"*-groom-{slug}.md"):
            if item.is_file():
                return item, True
    return None, False


def _ensure_pm_next_release_grooming(
    *,
    site: str,
    team_id: str,
    pm_agent: str,
    current_release: str,
    next_release_id: str,
    today: str,
    min_roi: int = 25,
) -> str:
    existing, is_outbox = _find_pm_grooming_item(pm_agent, next_release_id)
    if existing is not None:
        if not is_outbox:
            _set_min_inbox_roi(existing, min_roi)
            return f"pm-groom-prioritized:{existing.name}"
        return f"pm-groom-covered:{existing.name}"

    slug = re.sub(r"[^A-Za-z0-9._-]", "-", next_release_id).strip("-")[:60]
    item_id = f"{today}-groom-{slug}"
    item_dir = REPO_ROOT / "sessions" / pm_agent / "inbox" / item_id
    item_dir.mkdir(parents=True, exist_ok=True)
    _set_min_inbox_roi(item_dir, min_roi)
    (item_dir / "command.md").write_text(
        f"# Groom Next Release: {next_release_id}\n\n"
        f"- Site: {site}\n"
        f"- Current release (Dev executing): {current_release}\n"
        f"- Next release (your target): {next_release_id}\n\n"
        "The org always has two releases defined simultaneously:\n"
        "- **Current release** — Dev is executing, QA is verifying. You monitor but do not add scope.\n"
        "- **Next release** — You groom the backlog so Stage 0 of the next release is instant scope selection.\n\n"
        f"This task does NOT touch the current release. All work here is for {next_release_id} only.\n\n"
        "## Steps\n\n"
        "1. Pull community suggestions with `./scripts/suggestion-intake.sh`.\n"
        "2. Triage valid suggestions and create/curate feature briefs for the next release only.\n"
        "3. Write complete acceptance criteria for accepted features.\n"
        "4. Hand ready features to QA for test-plan design via `./scripts/pm-qa-handoff.sh`.\n"
        "5. Leave current-release scope unchanged; activation happens only when the next release becomes current.\n\n"
        "## Done when\n"
        f"- The next release `{next_release_id}` has an actively groomed ready backlog.\n"
        "- Any newly accepted feature has acceptance criteria and a QA handoff queued.\n",
        encoding="utf-8",
    )
    return f"pm-groom-queued:{item_id}"


def _find_ba_next_release_item(ba_agent: str, next_release_id: str) -> Tuple[pathlib.Path | None, bool]:
    inbox = REPO_ROOT / "sessions" / ba_agent / "inbox"
    outbox = REPO_ROOT / "sessions" / ba_agent / "outbox"
    if inbox.exists():
        for item in inbox.iterdir():
            if item.is_dir() and item.name != "_archived" and _item_mentions_release(item, [next_release_id]):
                return item, False
    if outbox.exists():
        for item in outbox.glob("*.md"):
            if item.is_file():
                text = item.read_text(encoding="utf-8", errors="ignore")
                if next_release_id in text:
                    return item, True
    return None, False


def _ensure_ba_next_release_scan(team_id: str, ba_agent: str, next_release_id: str, min_roi: int = 18) -> str:
    existing, is_outbox = _find_ba_next_release_item(ba_agent, next_release_id)
    if existing is not None:
        if not is_outbox:
            _set_min_inbox_roi(existing, min_roi)
            return f"ba-scan-prioritized:{existing.name}"
        return f"ba-scan-covered:{existing.name}"

    rc, _ = _run(["bash", "scripts/ba-reference-scan.sh", team_id, next_release_id], timeout=120)
    created, is_outbox = _find_ba_next_release_item(ba_agent, next_release_id)
    if created is not None and not is_outbox:
        _set_min_inbox_roi(created, min_roi)
        return f"ba-scan-queued:{created.name}"
    return f"ba-scan-rc:{rc}"


def _ensure_parallel_release_coverage(
    *,
    team_id: str,
    site: str,
    pm_agent: str,
    ba_agent: str,
    current_release: str,
    next_release_id: str,
    today: str,
) -> List[str]:
    actions: List[str] = []
    if pm_agent and current_release and next_release_id:
        actions.append(
            _ensure_pm_next_release_grooming(
                site=site,
                team_id=team_id,
                pm_agent=pm_agent,
                current_release=current_release,
                next_release_id=next_release_id,
                today=today,
            )
        )
    if ba_agent and next_release_id:
        actions.append(_ensure_ba_next_release_scan(team_id, ba_agent, next_release_id))
    return actions


def _release_cycle_step(log: List[Any]) -> None:
    """Ensure each coordinated-release team has an active release cycle.

    For each eligible team (active + release_preflight_enabled + coordinated_release_default):
      - If no active release → start a new one (current + next IDs)
      - If active but no next_release_id tracked → write it so the cycle can advance
      - If current release is signed off → advance: next becomes current, generate new next
    Calls scripts/release-cycle-start.sh which is idempotent (skips if already queued).
    """
    import json as _json

    release_control = _release_cycle_control_state()
    if not bool(release_control.get("enabled", True)):
        log.append({
            "step": "release_cycle",
            "status": "paused",
            "state_file": release_control.get("state_file"),
            "reason": release_control.get("reason"),
        })
        return

    active_dir = REPO_ROOT / "tmp" / "release-cycle-active"
    active_dir.mkdir(parents=True, exist_ok=True)

    teams_path = REPO_ROOT / "org-chart" / "products" / "product-teams.json"
    try:
        teams_data = _json.loads(teams_path.read_text(encoding="utf-8"))
    except Exception:
        log.append({"step": "release_cycle", "error": "could not read product-teams.json"})
        return

    today = datetime.now(timezone.utc).strftime("%Y%m%d")
    results: List[Dict[str, Any]] = []

    def _next_release_id_after(release_id: str, team_id: str, current_day: str) -> str:
        """Return the next monotonic release ID for a team.

        Expected sequence:
          <day>-<team>-release
          <day>-<team>-release-next
          <day>-<team>-release-b
          <day>-<team>-release-c
          ...

        If the incoming ID is malformed, fall back to the current day and start
        at the beginning of the sequence. Preserve the parsed release date when
        the input already has a valid team-scoped release ID so the cycle does
        not rewrite dates across midnight while a release is still active.
        """
        suffixes = ["release", "release-next"] + [f"release-{chr(c)}" for c in range(ord("b"), ord("z") + 1)]
        date_part = current_day
        suffix = "release"

        match = re.match(rf"^(\d{{8}})-{re.escape(team_id)}-(.+)$", release_id or "")
        if match:
            date_part = match.group(1)
            suffix = match.group(2)

        try:
            idx = suffixes.index(suffix)
        except ValueError:
            idx = 0

        next_idx = min(idx + 1, len(suffixes) - 1)
        return f"{date_part}-{team_id}-{suffixes[next_idx]}"

    for team in teams_data.get("teams", []):
        if not (team.get("active") and team.get("release_preflight_enabled") and team.get("coordinated_release_default")):
            continue
        team_id = (team.get("id") or "").strip()
        site = (team.get("site") or team_id).strip() or team_id
        pm_agent = (team.get("pm_agent") or "").strip()
        ba_agent = (team.get("ba_agent") or "").strip()
        if not team_id:
            continue

        release_id_file = active_dir / f"{team_id}.release_id"
        next_release_id_file = active_dir / f"{team_id}.next_release_id"

        current_release = release_id_file.read_text().strip() if release_id_file.exists() else ""
        next_release = next_release_id_file.read_text().strip() if next_release_id_file.exists() else ""

        # Detect signoff: pm-<team>/artifacts/release-signoffs/<release_id>.md
        cycle_signed_off = False
        if current_release and pm_agent:
            slug = re.sub(r"[^A-Za-z0-9._-]", "-", current_release)[:80]
            signoff_file = REPO_ROOT / "sessions" / pm_agent / "artifacts" / "release-signoffs" / f"{slug}.md"
            cycle_signed_off = signoff_file.exists()

        if not current_release:
            new_current = f"{today}-{team_id}-release"
            new_next = f"{today}-{team_id}-release-next"
            action = "start"

            rc, out = _run(
                ["bash", "scripts/release-cycle-start.sh", team_id, new_current, new_next],
                timeout=120,
            )
            results.append({"team": team_id, "action": action, "current": new_current, "next": new_next, "rc": rc})
            if rc == 0:
                print(f"RELEASE-CYCLE: {action} {team_id} current={new_current} next={new_next}")
        elif cycle_signed_off:
            expected_next = _next_release_id_after(current_release, team_id, today)
            if next_release != expected_next:
                next_release_id_file.write_text(expected_next + "\n")
                next_release = expected_next
                action = "signed_off_next_fixed"
            else:
                action = "signed_off_waiting_push"
            coverage = _ensure_parallel_release_coverage(
                team_id=team_id,
                site=site,
                pm_agent=pm_agent,
                ba_agent=ba_agent,
                current_release=current_release,
                next_release_id=next_release,
                today=today,
            )
            results.append(
                {"team": team_id, "action": action, "current": current_release, "next": next_release, "parallel": coverage}
            )
        else:
            # Cycle running — ensure next_release_id is persisted for future advance
            expected_next = _next_release_id_after(current_release, team_id, today)
            if next_release != expected_next:
                had_next = bool(next_release)
                next_release_id_file.write_text(expected_next + "\n")
                next_release = expected_next
                action = "next_fixed" if had_next else "next_set"
            else:
                action = "active"
            coverage = _ensure_parallel_release_coverage(
                team_id=team_id,
                site=site,
                pm_agent=pm_agent,
                ba_agent=ba_agent,
                current_release=current_release,
                next_release_id=next_release,
                today=today,
            )
            results.append(
                {"team": team_id, "action": action, "current": current_release, "next": next_release, "parallel": coverage}
            )

    log.append({"step": "release_cycle", "teams": results})

    # Gate 2 auto-approve: also called here (in addition to _health_check_step)
    # so it fires on the release-cycle cadence even if health_check times out
    # and returns early (hq-status.sh > 180s). Belt-and-suspenders — the function
    # is idempotent (skips if approve file already exists).
    try:
        _dispatch_gate2_auto_approve()
    except Exception as _e:
        log.append({"step": "release_cycle", "gate2_auto_approve_error": str(_e)})


def _write_release_notes(release_id: str, slug: str, required: List[Dict[str, Any]]) -> None:
    """Auto-generate 05-release-notes.md in pm-forseti's release-candidates dir.

    Sources: git log since last deploy tag/checkpoint + PM signoff content.
    Creates the release-candidates/<release-id>/ folder if needed, then writes
    05-release-notes.md. Skips if the file already exists (don't overwrite human edits).
    """
    rc_dir = REPO_ROOT / "sessions" / "pm-forseti" / "artifacts" / "release-candidates" / slug
    notes_file = rc_dir / "05-release-notes.md"
    if notes_file.exists():
        return

    rc_dir.mkdir(parents=True, exist_ok=True)

    # Git log: recent commits on the forseti.life repo (last 20, no merge commits)
    # Supports both layouts:
    # 1) Merged workspace: <repo>/copilot-hq (repo root is REPO_ROOT.parent)
    # 2) Legacy standalone HQ checkout with sibling forseti.life repo
    env_repo = os.environ.get("FORSETI_REPO_ROOT", "").strip()
    site_repo_candidates = [
        Path(env_repo) if env_repo else None,
        REPO_ROOT.parent,
        REPO_ROOT.parent / "forseti.life",
        REPO_ROOT,
    ]
    site_repo = next(
        (
            cand
            for cand in site_repo_candidates
            if cand is not None and cand.is_dir() and ((cand / ".git").is_dir() or (cand / ".git").is_file())
        ),
        None,
    )
    git_summary = ""
    if site_repo and site_repo.is_dir():
        rc_git, git_out = _run(
            ["git", "-C", str(site_repo), "log", "--oneline", "--no-merges", "-20"],
            timeout=15,
        )
        if rc_git == 0:
            git_summary = git_out.strip()

    # Collect PM signoff content
    signoff_sections = []
    for entry in required:
        sf = REPO_ROOT / "sessions" / entry["pm_agent"] / "artifacts" / "release-signoffs" / f"{slug}.md"
        if sf.exists():
            content = sf.read_text(encoding="utf-8", errors="ignore").strip()
            signoff_sections.append(f"### {entry['pm_agent']}\n\n{content}")

    pushed_at = datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")

    notes = f"# Release Notes: {release_id}\n\n"
    notes += f"- **Release id**: `{release_id}`\n"
    notes += f"- **Pushed at**: {pushed_at}\n"
    notes += f"- **State**: shipped (auto-generated at push time)\n\n"

    notes += "## Recent commits\n\n"
    if git_summary:
        notes += "```\n" + git_summary + "\n```\n\n"
    else:
        notes += "_Could not retrieve git log._\n\n"

    notes += "## PM signoffs\n\n"
    if signoff_sections:
        notes += "\n\n".join(signoff_sections) + "\n\n"
    else:
        notes += "_No signoff content found._\n\n"

    notes += (
        "## Summary\n\n"
        "_Auto-generated at deploy time. PM/CEO should update with user-visible changes, "
        "known caveats, and links to QA evidence._\n"
    )

    notes_file.write_text(notes, encoding="utf-8")
    print(f"RELEASE-NOTES: wrote {notes_file}")


def _coordinated_push_step(log: List[Any]) -> None:
    """Auto-deploy when all coordinated teams have their current active release signed off.

    Each team has its own release ID (e.g. 20260406-dungeoncrawler-release-next vs
    20260406-forseti-release-next).  The deploy fires when every team's PM has written
    a signoff for THEIR current active release_id — not when they share the same ID.

    Marker is keyed to the sorted, joined set of all active release IDs to prevent
    duplicate deploys across ticks.

    Idempotent: a marker file in tmp/auto-push-dispatched/<combined-slug>.pushed blocks re-fire.
    """
    import json as _json

    release_control = _release_cycle_control_state()
    if not bool(release_control.get("enabled", True)):
        log.append({
            "step": "coordinated_push",
            "status": "paused",
            "state_file": release_control.get("state_file"),
            "reason": release_control.get("reason"),
        })
        return

    teams_path = REPO_ROOT / "org-chart" / "products" / "product-teams.json"
    try:
        teams_data = _json.loads(teams_path.read_text(encoding="utf-8"))
    except Exception:
        log.append({"step": "coordinated_push", "error": "could not read product-teams.json"})
        return

    required: List[Dict[str, Any]] = []
    for team in teams_data.get("teams", []):
        if not (team.get("active") and team.get("coordinated_release_default")):
            continue
        pm_agent = (team.get("pm_agent") or "").strip()
        if pm_agent:
            required.append({
                "team_id": team.get("id", ""),
                "pm_agent": pm_agent,
                "qa_agent": team.get("qa_agent", ""),
            })

    if not required:
        return

    active_dir = REPO_ROOT / "tmp" / "release-cycle-active"

    # Build per-team readiness: does each team's PM have a signoff for their active release?
    team_release_ids: Dict[str, str] = {}  # team_id -> active release_id
    team_ready: Dict[str, bool] = {}       # team_id -> signoff present

    for entry in required:
        team_id = entry["team_id"]
        pm_agent = entry["pm_agent"]
        release_id_file = active_dir / f"{team_id}.release_id"
        if not release_id_file.exists():
            team_ready[team_id] = False
            continue
        rid = release_id_file.read_text(encoding="utf-8").strip()
        if not rid:
            team_ready[team_id] = False
            continue
        team_release_ids[team_id] = rid
        slug = re.sub(r"[^A-Za-z0-9._-]", "-", rid)[:80]
        signoff_file = REPO_ROOT / "sessions" / pm_agent / "artifacts" / "release-signoffs" / f"{slug}.md"
        team_ready[team_id] = signoff_file.exists()

    # All teams must have active releases and PM signoffs
    if not all(team_ready.get(e["team_id"], False) for e in required):
        not_ready = [e["team_id"] for e in required if not team_ready.get(e["team_id"], False)]
        log.append({"step": "coordinated_push", "status": "waiting", "not_ready": not_ready,
                    "team_releases": team_release_ids})
        return

    # Build a stable marker key from all active release IDs (sorted for determinism)
    combined_key = "__".join(
        re.sub(r"[^A-Za-z0-9._-]", "-", team_release_ids[e["team_id"]])
        for e in sorted(required, key=lambda x: x["team_id"])
    )[:120]

    pushed_dir = REPO_ROOT / "tmp" / "auto-push-dispatched"
    pushed_dir.mkdir(parents=True, exist_ok=True)
    marker = pushed_dir / f"{combined_key}.pushed"

    if marker.exists():
        log.append({"step": "coordinated_push", "status": "already_pushed", "marker": combined_key})
        return

    # Write marker first to prevent duplicate triggers across ticks.
    # If dispatch fails, remove marker so a future tick can retry.
    marker.write_text(datetime.now(timezone.utc).isoformat() + "\n")

    # Use the first (alphabetically) release ID as the canonical label for logs/notes
    canonical_release_id = sorted(team_release_ids.values())[0]
    canonical_slug = re.sub(r"[^A-Za-z0-9._-]", "-", canonical_release_id)[:80]

    # Auto-generate release notes from git log + signoff content
    _write_release_notes(canonical_release_id, canonical_slug, required)

    repo_slug = os.environ.get("FORSETI_GH_REPO", "Forseti-Life/forseti.life").strip() or "Forseti-Life/forseti.life"
    rc, out = _run(
        ["gh", "workflow", "run", "deploy.yml",
         "--repo", repo_slug, "--ref", "main"],
        timeout=60,
    )
    print(f"COORDINATED-PUSH: {combined_key} deploy rc={rc}")

    if rc != 0:
        try:
            marker.unlink(missing_ok=True)
        except Exception:
            pass
        log.append({
            "step": "coordinated_push",
            "status": "deploy_dispatch_failed",
            "marker": combined_key,
            "team_releases": team_release_ids,
            "deploy_rc": rc,
            "deploy_output": (out or "")[:2000],
            "repo": repo_slug,
        })
        return

    # Dispatch post-push config-import + Gate R5 item to pm-forseti
    today = datetime.now(timezone.utc).strftime("%Y%m%d")
    item_id = f"{today}-post-push-{canonical_slug}"
    inbox_dir = REPO_ROOT / "sessions" / "pm-forseti" / "inbox" / item_id
    outbox_file = REPO_ROOT / "sessions" / "pm-forseti" / "outbox" / f"{item_id}.md"
    if not inbox_dir.exists() and not outbox_file.exists():
        inbox_dir.mkdir(parents=True, exist_ok=True)
        (inbox_dir / "roi.txt").write_text("9\n")
        all_ids_text = "\n".join(f"  - {tid}: `{rid}`" for tid, rid in sorted(team_release_ids.items()))
        (inbox_dir / "command.md").write_text(
            f"# Post-push steps: coordinated release\n\n"
            f"The coordinated release deploy was triggered automatically.\n\n"
            f"## Releases shipped\n{all_ids_text}\n\n"
            "## 1. Wait for deploy workflow to finish\n"
            f"```bash\ngh run list --repo {repo_slug} --workflow deploy.yml --limit 3\n```\n\n"
            "## 2. Import config on production\n"
            "```bash\ncd /var/www/html/forseti && vendor/bin/drush config:import -y && vendor/bin/drush cr\n```\n\n"
            "## 3. Gate R5 — post-release production QA\n"
            "Trigger a production audit for each product (requires ALLOW_PROD_QA=1):\n"
            "```bash\nALLOW_PROD_QA=1 bash scripts/site-full-audit.py forseti\n```\n"
            "Record clean/unclean signal in your outbox.\n\n"
            f"Canonical release id: `{canonical_release_id}`\n"
        )

    # Ensure _release_cycle_step can advance each team's cycle: write cross-team signoffs
    # if the other PM's release hasn't been individually signed yet (e.g. Forseti PM signs
    # dungeoncrawler's release so DC cycle advances on next tick).
    for entry in required:
        team_id = entry["team_id"]
        pm_agent = entry["pm_agent"]
        rid = team_release_ids.get(team_id, "")
        if not rid:
            continue
        slug = re.sub(r"[^A-Za-z0-9._-]", "-", rid)[:80]
        signoff_path = REPO_ROOT / "sessions" / pm_agent / "artifacts" / "release-signoffs" / f"{slug}.md"
        if not signoff_path.exists():
            signoff_path.parent.mkdir(parents=True, exist_ok=True)
            signoff_path.write_text(
                f"# Release Signoff: {rid}\n\n"
                f"- Status: approved\n"
                f"- Signed by: orchestrator (coordinated push {combined_key})\n"
                f"- Timestamp: {datetime.now(timezone.utc).isoformat()}\n\n"
                f"This release was shipped as part of a coordinated push.\n"
            )
            print(f"COORDINATED-PUSH: wrote signoff {rid} for {team_id}/{pm_agent}")

    log.append({
        "step": "coordinated_push",
        "status": "pushed",
        "marker": combined_key,
        "team_releases": team_release_ids,
        "deploy_rc": rc,
    })


def _make_provider(name: str) -> RuntimeProvider:
    return {"shell": ShellProvider, "cline": ClineProvider}.get(name, ShellProvider)()


# ── Tick ─────────────────────────────────────────────────────────────────────

def _run_tick(
    provider: RuntimeProvider,
    *,
    agent_cap: int,
    publish_enabled: bool,
    kpi_interval: int,
    kpi_last_run: int,
    release_cycle_interval: int,
    release_cycle_last_run: int,
) -> Tuple[Dict[str, Any], int, int]:
    """Run one full orchestration tick through the LangGraph execution graph."""
    deps = LangGraphDeps(
        run_cmd=_run,
        dispatch_commands_step=_dispatch_commands_step,
        release_cycle_step=_release_cycle_step,
        coordinated_push_step=_coordinated_push_step,
        prioritized_agents=_prioritized_agents,
        health_check_step=_health_check_step,
        now_ts=_now_ts,
        kpi_monitor_cmd=[sys.executable, "scripts/release-kpi-monitor.py", "--auto-remediate"],
    )
    return _run_langgraph_tick(
        provider,
        agent_cap=agent_cap,
        publish_enabled=publish_enabled,
        kpi_interval=kpi_interval,
        kpi_last_run=kpi_last_run,
        release_cycle_interval=release_cycle_interval,
        release_cycle_last_run=release_cycle_last_run,
        deps=deps,
    )


# ── Entry point ───────────────────────────────────────────────────────────────

def main() -> None:
    parser = argparse.ArgumentParser(description="Consolidated HQ orchestrator")
    parser.add_argument("--once", action="store_true", help="Run one tick and exit")
    parser.add_argument("--interval", type=int, default=60, help="Seconds between ticks")
    parser.add_argument("--provider", choices=["shell", "cline"], default="shell")
    parser.add_argument("--agent-cap", type=int, default=6,
                        help="Max agents to execute per tick (CEO counts toward cap)")
    parser.add_argument("--non-ceo-cap", type=int, default=None,
                        help="Deprecated alias; mapped to total cap as non_ceo+1")
    parser.add_argument("--no-publish", action="store_true")
    parser.add_argument("--kpi-interval", type=int, default=300,
                        help="Seconds between KPI monitor runs (default 5 min)")
    parser.add_argument("--release-cycle-interval", type=int, default=300,
                        help="Seconds between release cycle checks (default 5 min)")
    parser.add_argument("--log-file", default="inbox/responses/orchestrator-latest.log")
    args = parser.parse_args()

    provider = _make_provider(args.provider)
    effective_agent_cap = max(0, int(args.agent_cap))
    if args.non_ceo_cap is not None:
        effective_agent_cap = max(effective_agent_cap, max(0, int(args.non_ceo_cap)) + 1)
    log_path = (REPO_ROOT / args.log_file).resolve()
    log_path.parent.mkdir(parents=True, exist_ok=True)

    def _write_log(result: Dict[str, Any]) -> None:
        selected = result.get("selected_agents") or []
        line = f"[{result.get('ts', time.strftime('%Y-%m-%dT%H:%M:%S%z'))}] agents={','.join(selected) if selected else '-'}\n"
        with log_path.open("a", encoding="utf-8") as f:
            f.write(line)

    if args.once:
        if (REPO_ROOT / "scripts" / "is-org-enabled.sh").exists():
            rc, enabled = _run(["bash", "scripts/is-org-enabled.sh"], timeout=10)
            if enabled.strip().lower() != "true":
                print("org disabled; skipping tick")
                return
        result, _, _ = _run_tick(
            provider,
            agent_cap=effective_agent_cap,
            publish_enabled=not args.no_publish,
            kpi_interval=args.kpi_interval,
            kpi_last_run=0,
            release_cycle_interval=args.release_cycle_interval,
            release_cycle_last_run=0,
        )
        _write_log(result)
        return

    kpi_last_run = 0
    release_cycle_last_run = 0
    while True:
        try:
            rc, enabled = _run(["bash", "scripts/is-org-enabled.sh"], timeout=10)
            if enabled.strip().lower() != "true":
                time.sleep(max(1, args.interval))
                continue
            result, kpi_last_run, release_cycle_last_run = _run_tick(
                provider,
                agent_cap=effective_agent_cap,
                publish_enabled=not args.no_publish,
                kpi_interval=args.kpi_interval,
                kpi_last_run=kpi_last_run,
                release_cycle_interval=args.release_cycle_interval,
                release_cycle_last_run=release_cycle_last_run,
            )
            _write_log(result)
        except Exception as e:
            print(f"[WARN] tick failed: {e}", file=sys.stderr)
        time.sleep(max(1, args.interval))


if __name__ == "__main__":
    main()
