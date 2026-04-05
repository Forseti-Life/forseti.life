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


def _agent_inbox_count(agent_id: str) -> int:
    d = _agent_inbox_dir(agent_id)
    if not d.is_dir():
        return 0
    return sum(1 for p in d.iterdir() if p.is_dir() and p.name != "_archived")


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


def _agent_has_release_work(agent_id: str, release_ids: List[str]) -> bool:
    """Return True if the agent has any inbox item tagged for an active release."""
    if not release_ids:
        return False
    inbox = _agent_inbox_dir(agent_id)
    if not inbox.exists():
        return False
    for item in inbox.iterdir():
        if item.is_dir() and item.name != "_archived":
            for rid in release_ids:
                if rid in item.name:
                    return True
    return False


def _prioritized_agents() -> List[ScheduledAgent]:
    """All agents (CEO included) with inbox items, sorted by release-work first,
    then by level then ROI.

    During an active release cycle, agents with inbox items tagged for the
    active release ID are promoted to the front of the queue so they always
    consume execution slots before non-release work.
    """
    priorities = _load_org_priorities()
    release_ids = _active_release_ids()
    agents = []
    for agent_id in _load_agents_yaml_ids():
        if _is_agent_paused(agent_id):
            continue
        if _agent_inbox_count(agent_id) <= 0:
            continue
        inbox = _agent_inbox_dir(agent_id)
        top = max(
            (_item_effective_roi(p, p.name, priorities=priorities) for p in inbox.iterdir() if p.is_dir() and p.name != "_archived"),
            default=1,
        )
        has_release = _agent_has_release_work(agent_id, release_ids)
        agents.append(ScheduledAgent(
            agent_id=agent_id,
            level=_agent_level_weight(_role_for_agent(agent_id)),
            top_roi=top,
            has_release_work=has_release,
        ))
    # Sort: release-work agents first, then by level desc, then by top ROI desc.
    agents.sort(key=lambda a: (0 if a.has_release_work else 1, -a.level, -a.top_roi, a.agent_id))
    return agents


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
        pm = _parse_md_field(content, "pm")
        work_item = _parse_md_field(content, "work_item")
        topic = _parse_md_field(content, "topic") or f.stem

        dest = processed_dir / f.name

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
            if not item_dir.is_dir():
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

    # Always check for lagging PM signoffs regardless of blocked count
    try:
        _dispatch_signoff_reminders()
    except Exception as e:
        print(f"SIGNOFF-REMINDER-ERR: {e}")

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


def _release_cycle_step(log: List[Any]) -> None:
    """Ensure each coordinated-release team has an active release cycle.

    For each eligible team (active + release_preflight_enabled + coordinated_release_default):
      - If no active release → start a new one (current + next IDs)
      - If active but no next_release_id tracked → write it so the cycle can advance
      - If current release is signed off → advance: next becomes current, generate new next
    Calls scripts/release-cycle-start.sh which is idempotent (skips if already queued).
    """
    import json as _json

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

    for team in teams_data.get("teams", []):
        if not (team.get("active") and team.get("release_preflight_enabled") and team.get("coordinated_release_default")):
            continue
        team_id = (team.get("id") or "").strip()
        pm_agent = (team.get("pm_agent") or "").strip()
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

        if not current_release or cycle_signed_off:
            # Start or advance the release cycle
            if cycle_signed_off and next_release:
                new_current = next_release
                # Generate a unique next ID — avoid colliding with the just-promoted current.
                # Cycle through suffixes (-release-b, -release-c, ...) until distinct.
                _suffixes = ["release-b", "release-c", "release-d", "release-e", "release-f"]
                new_next = next(
                    (f"{today}-{team_id}-{s}" for s in _suffixes if f"{today}-{team_id}-{s}" != new_current),
                    f"{today}-{team_id}-release-b",
                )
                action = "advance"
            else:
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
                # Post-release process review: when a cycle advances after signoff,
                # dispatch PM+CEO review items to close process gaps from the
                # just-finished release.
                if action == "advance":
                    _run(
                        ["bash", "scripts/improvement-round.sh", today, f"improvement-round-{new_current}"],
                        timeout=60,
                    )
        else:
            # Cycle running — ensure next_release_id is persisted for future advance
            if not next_release:
                new_next = f"{today}-{team_id}-release-next"
                next_release_id_file.write_text(new_next + "\n")
                results.append({"team": team_id, "action": "next_set", "current": current_release, "next": new_next})
            else:
                results.append({"team": team_id, "action": "active", "current": current_release, "next": next_release})

    log.append({"step": "release_cycle", "teams": results})


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
    """Auto-deploy when all required coordinated PM signoffs are present for a release ID.

    Scans each required PM agent's release-signoffs/ dir, finds release IDs present in ALL
    of them, and for each unseen release ID triggers `gh workflow run deploy.yml` and
    dispatches a post-push config-import + Gate R5 inbox item to pm-forseti.

    Idempotent: a marker file in tmp/auto-push-dispatched/{slug}.pushed prevents re-triggering.
    """
    import json as _json

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

    signoffs_by_agent: Dict[str, set] = {}
    for entry in required:
        pm_agent = entry["pm_agent"]
        signoff_dir = REPO_ROOT / "sessions" / pm_agent / "artifacts" / "release-signoffs"
        ids: set = set()
        if signoff_dir.is_dir():
            for f in signoff_dir.iterdir():
                if f.suffix == ".md" and not f.stem.startswith("_"):
                    ids.add(f.stem)
        signoffs_by_agent[pm_agent] = ids

    all_sets = [signoffs_by_agent[e["pm_agent"]] for e in required]
    ready_releases = set.intersection(*all_sets) if all_sets else set()

    pushed_dir = REPO_ROOT / "tmp" / "auto-push-dispatched"
    pushed_dir.mkdir(parents=True, exist_ok=True)

    results = []
    for release_id in sorted(ready_releases):
        slug = re.sub(r"[^A-Za-z0-9._-]", "-", release_id)[:80]
        marker = pushed_dir / f"{slug}.pushed"
        if marker.exists():
            results.append({"release_id": release_id, "action": "already_pushed"})
            continue

        # Write marker first to prevent duplicate triggers across ticks
        marker.write_text(datetime.now(timezone.utc).isoformat() + "\n")

        # Auto-generate release notes from git log + signoff content
        _write_release_notes(release_id, slug, required)

        rc, out = _run(
            ["gh", "workflow", "run", "deploy.yml",
             "--repo", "keithaumiller/forseti.life", "--ref", "main"],
            timeout=60,
        )
        print(f"COORDINATED-PUSH: {release_id} deploy rc={rc}")

        # Dispatch post-push config-import + Gate R5 item to pm-forseti
        today = datetime.now(timezone.utc).strftime("%Y%m%d")
        item_id = f"{today}-post-push-{slug}"
        inbox_dir = REPO_ROOT / "sessions" / "pm-forseti" / "inbox" / item_id
        outbox_file = REPO_ROOT / "sessions" / "pm-forseti" / "outbox" / f"{item_id}.md"
        if not inbox_dir.exists() and not outbox_file.exists():
            inbox_dir.mkdir(parents=True, exist_ok=True)
            (inbox_dir / "roi.txt").write_text("9\n")
            (inbox_dir / "command.md").write_text(
                f"# Post-push steps: {release_id}\n\n"
                "The coordinated release deploy was triggered automatically. Complete post-push steps:\n\n"
                "## 1. Wait for deploy workflow to finish\n"
                "```bash\ngh run list --repo keithaumiller/forseti.life --workflow deploy.yml --limit 3\n```\n\n"
                "## 2. Import config on production\n"
                "```bash\ncd /var/www/html/forseti && vendor/bin/drush config:import -y && vendor/bin/drush cr\n```\n\n"
                "## 3. Gate R5 — post-release production QA\n"
                "Trigger a production audit for each product (requires ALLOW_PROD_QA=1):\n"
                "```bash\nALLOW_PROD_QA=1 bash scripts/site-full-audit.py forseti\n```\n"
                "Record clean/unclean signal in your outbox.\n\n"
                f"Release id: `{release_id}`\n"
            )

        # Write per-team signoffs so _release_cycle_step advances the cycle
        active_dir = REPO_ROOT / "tmp" / "release-cycle-active"
        for entry in required:
            team_id = entry["team_id"]
            pm_agent = entry["pm_agent"]
            per_team_release_id_file = active_dir / f"{team_id}.release_id"
            if per_team_release_id_file.exists():
                per_team_release_id = per_team_release_id_file.read_text().strip()
                if per_team_release_id:
                    per_team_signoff = (
                        REPO_ROOT / "sessions" / pm_agent / "artifacts" / "release-signoffs"
                        / f"{re.sub(r'[^A-Za-z0-9._-]', '-', per_team_release_id)[:80]}.md"
                    )
                    if not per_team_signoff.exists():
                        per_team_signoff.write_text(
                            f"# Release Signoff: {per_team_release_id}\n\n"
                            f"**Status**: signed-off\n"
                            f"**Signed by**: orchestrator (coordinated release {release_id} shipped)\n\n"
                            f"This per-team release was shipped as part of coordinated release `{release_id}`.\n"
                        )
                        print(f"COORDINATED-PUSH: wrote per-team signoff {per_team_release_id} for {team_id}")

        results.append({"release_id": release_id, "action": "pushed", "deploy_rc": rc})

    log.append({"step": "coordinated_push", "ready_releases": list(ready_releases), "results": results})


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
    parser.add_argument("--agent-cap", type=int, default=4,
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
