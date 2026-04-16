from __future__ import annotations

import json
import os
import pathlib
from dataclasses import dataclass
from datetime import datetime, timezone
from typing import Any, Callable, Dict, List, Tuple

from langgraph.graph import StateGraph  # type: ignore


TickResult = Tuple[Dict[str, Any], int, int]
RunFn = Callable[..., Tuple[int, str]]
DispatchFn = Callable[[List[Any]], None]
ReleaseCycleFn = Callable[[List[Any]], None]
CoordinatedPushFn = Callable[[List[Any]], None]
PrioritizedAgentsFn = Callable[[], List[Any]]
HealthCheckFn = Callable[[Any, List[Any]], None]
NowTsFn = Callable[[], int]


@dataclass(frozen=True)
class LangGraphDeps:
    run_cmd: RunFn
    dispatch_commands_step: DispatchFn
    release_cycle_step: ReleaseCycleFn
    coordinated_push_step: CoordinatedPushFn
    prioritized_agents: PrioritizedAgentsFn
    health_check_step: HealthCheckFn
    now_ts: NowTsFn
    kpi_monitor_cmd: List[str]


import subprocess as _subprocess


def _refresh_feature_progress(hq_root: pathlib.Path) -> None:
    """Regenerate dashboards/FEATURE_PROGRESS.md from features/*/feature.md. Best-effort."""
    script = hq_root / "scripts" / "generate-feature-progress.py"
    if script.exists():
        try:
            _subprocess.run(
                ["python3", str(script)],
                cwd=str(hq_root),
                timeout=30,
                check=False,
                capture_output=True,
            )
        except Exception:  # noqa: BLE001
            pass


def _write_tick_telemetry(state: Dict[str, Any]) -> None:
    """Append a tick record to langgraph-ticks.jsonl for the Drupal dashboard."""
    hq_root = pathlib.Path(os.environ.get("COPILOT_HQ_ROOT", str(pathlib.Path(__file__).parent.parent.parent)))
    responses_dir = hq_root / "inbox" / "responses"
    responses_dir.mkdir(parents=True, exist_ok=True)

    log_entries: List[Dict[str, Any]] = state.get("log", [])

    # Build step_results dict keyed by step name (for dashboard per-node display).
    step_results: Dict[str, Any] = {}
    for entry in log_entries:
        step_name = entry.get("step")
        if step_name:
            step_results[step_name] = {k: v for k, v in entry.items() if k != "step"}

    # Collect top-level errors from any step with non-zero rc or explicit error key.
    errors: List[str] = []
    for entry in log_entries:
        if entry.get("rc", 0) != 0:
            errors.append(f"step {entry.get('step', '?')}: rc={entry['rc']}")
        if "error" in entry:
            errors.append(f"step {entry.get('step', '?')}: {entry['error']}")

    # Synthetic summarize_tick step expected by the troubleshooting panel.
    step_results["summarize_tick"] = {
        "selected_agents": state.get("selected_agents", []),
        "org_enabled": True,
        "errors": errors,
    }

    tick_record = {
        "ts": state.get("ts"),
        "dry_run": not state.get("publish_enabled", True),
        "publish_enabled": state.get("publish_enabled", True),
        "agent_cap": state.get("agent_cap", 0),
        "provider": str(state.get("provider", "")),
        "selected_agents": state.get("selected_agents", []),
        "errors": errors,
        "step_results": step_results,
    }

    ticks_file = responses_dir / "langgraph-ticks.jsonl"
    with open(ticks_file, "a", encoding="utf-8") as fh:
        fh.write(json.dumps(tick_record) + "\n")

    # Keep at most 500 lines to prevent unbounded growth.
    try:
        lines = ticks_file.read_text(encoding="utf-8").splitlines()
        if len(lines) > 500:
            ticks_file.write_text("\n".join(lines[-500:]) + "\n", encoding="utf-8")
    except OSError:
        pass

    # Build parity record with schema expected by DashboardController::langGraphParityHealth().
    expected_steps = [
        "consume_replies", "dispatch_commands", "release_cycle", "coordinated_push",
        "pick_agents", "exec_agents", "health_check", "publish",
    ]
    actual_steps = [entry.get("step") for entry in log_entries if "step" in entry]
    steps_match = set(expected_steps).issubset(set(actual_steps))

    # selected_agents parity: compare pick_agents log entry vs final selected_agents.
    picked = next(
        (entry.get("selected", []) for entry in log_entries if entry.get("step") == "pick_agents"),
        [],
    )
    agents_match = sorted(picked) == sorted(state.get("selected_agents", []))

    all_ok = all(entry.get("rc", 0) == 0 for entry in log_entries if "rc" in entry)

    parity_errors: List[str] = list(errors)
    if not steps_match:
        missing = set(expected_steps) - set(actual_steps)
        parity_errors.append(f"missing steps: {', '.join(sorted(missing))}")

    parity_record = {
        "generated_at": state.get("ts"),
        "parity_ok": bool(all_ok and steps_match),
        "selected_agents": {
            "match": agents_match,
            "actual": state.get("selected_agents", []),
        },
        "steps": {
            "match": steps_match,
            "expected": expected_steps,
            "actual": actual_steps,
        },
        "errors": parity_errors,
    }
    parity_file = responses_dir / "langgraph-parity-latest.json"
    parity_file.write_text(json.dumps(parity_record, indent=2) + "\n", encoding="utf-8")

    # Refresh FEATURE_PROGRESS.md so the dashboard always reflects current feature state.
    _refresh_feature_progress(hq_root)


def run_tick(
    provider: Any,
    *,
    agent_cap: int,
    publish_enabled: bool,
    kpi_interval: int,
    kpi_last_run: int,
    release_cycle_interval: int,
    release_cycle_last_run: int,
    deps: LangGraphDeps,
    kpi_max_output_chars: int = 500,
) -> TickResult:
    state: Dict[str, Any] = {
        "ts": datetime.now(timezone.utc).isoformat(),
        "log": [],
        "selected_agents": [],
        "agent_cap": max(0, int(agent_cap)),
        "publish_enabled": bool(publish_enabled),
        "kpi_interval": max(0, int(kpi_interval)),
        "kpi_last_run": int(kpi_last_run),
        "release_cycle_interval": max(0, int(release_cycle_interval)),
        "release_cycle_last_run": int(release_cycle_last_run),
        "provider": type(provider).__name__,
    }

    def consume_replies(s: Dict[str, Any]) -> Dict[str, Any]:
        rc, _ = deps.run_cmd(["bash", "scripts/consume-forseti-replies.sh"], timeout=300)
        s["log"].append({"step": "consume_replies", "rc": rc})
        return s

    def dispatch_commands(s: Dict[str, Any]) -> Dict[str, Any]:
        deps.dispatch_commands_step(s["log"])
        return s

    def release_cycle(s: Dict[str, Any]) -> Dict[str, Any]:
        if (deps.now_ts() - s["release_cycle_last_run"]) >= s["release_cycle_interval"]:
            deps.release_cycle_step(s["log"])
            s["release_cycle_last_run"] = deps.now_ts()
        return s

    def coordinated_push(s: Dict[str, Any]) -> Dict[str, Any]:
        deps.coordinated_push_step(s["log"])
        return s

    def pick_agents(s: Dict[str, Any]) -> Dict[str, Any]:
        all_agents = deps.prioritized_agents()
        ceo_agents = [a for a in all_agents if str(getattr(a, "agent_id", "")).startswith("ceo-copilot")][:1]
        other_agents = [a for a in all_agents if not str(getattr(a, "agent_id", "")).startswith("ceo-copilot")]
        other_cap = max(0, s["agent_cap"] - len(ceo_agents))
        # Respect the ordering returned by prioritized_agents(). It already
        # encodes: current-release first, then spare-capacity spillover into
        # next-release prep, then other work.
        selected_other = other_agents[:other_cap]

        agents = ceo_agents + selected_other
        selected = [str(getattr(a, "agent_id", "")) for a in agents]
        current_release = [str(getattr(a, "agent_id", "")) for a in selected_other if getattr(a, "has_release_work", False)]
        next_release = [str(getattr(a, "agent_id", "")) for a in selected_other if getattr(a, "has_next_release_work", False)]
        s["selected_agents"] = selected
        s["log"].append({
            "step": "pick_agents",
            "selected": selected,
            "release_priority": current_release,
            "next_release_spillover": next_release,
        })
        return s

    def exec_agents(s: Dict[str, Any]) -> Dict[str, Any]:
        ran: List[Dict[str, Any]] = []
        for agent_id in s.get("selected_agents") or []:
            rc_exec, _ = provider.run_one(str(agent_id))
            ran.append({"agent": agent_id, "rc": rc_exec})
        s["log"].append({"step": "exec_agents", "ran": ran})
        return s

    def health_check(s: Dict[str, Any]) -> Dict[str, Any]:
        deps.health_check_step(provider, s["log"])
        return s

    def kpi_monitor(s: Dict[str, Any]) -> Dict[str, Any]:
        if (deps.now_ts() - s["kpi_last_run"]) >= s["kpi_interval"]:
            rc_kpi, out_kpi = deps.run_cmd(deps.kpi_monitor_cmd, timeout=300)
            s["log"].append({"step": "kpi_monitor", "rc": rc_kpi, "out": out_kpi[:kpi_max_output_chars]})
            if "HANDOFF-GAP" in out_kpi:
                print(f"AUTO-HANDOFF: detected {out_kpi.count('HANDOFF-GAP')} HANDOFF-GAP state(s)")
            s["kpi_last_run"] = deps.now_ts()
        return s

    def publish(s: Dict[str, Any]) -> Dict[str, Any]:
        if s["publish_enabled"]:
            rc_pub, _ = deps.run_cmd(["bash", "scripts/publish-forseti-agent-tracker.sh"], timeout=1200)
            s["log"].append({"step": "publish", "rc": rc_pub})
        else:
            s["log"].append({"step": "publish", "skipped": True})

        # Write tick telemetry for the Drupal LangGraph dashboard.
        _write_tick_telemetry(s)

        return s

    graph = StateGraph(dict)
    graph.add_node("consume_replies", consume_replies)
    graph.add_node("dispatch_commands", dispatch_commands)
    graph.add_node("release_cycle", release_cycle)
    graph.add_node("coordinated_push", coordinated_push)
    graph.add_node("pick_agents", pick_agents)
    graph.add_node("exec_agents", exec_agents)
    graph.add_node("health_check", health_check)
    graph.add_node("kpi_monitor", kpi_monitor)
    graph.add_node("publish", publish)
    graph.set_entry_point("consume_replies")
    graph.add_edge("consume_replies", "dispatch_commands")
    graph.add_edge("dispatch_commands", "release_cycle")
    graph.add_edge("release_cycle", "coordinated_push")
    graph.add_edge("coordinated_push", "pick_agents")
    graph.add_edge("pick_agents", "exec_agents")
    graph.add_edge("exec_agents", "health_check")
    graph.add_edge("health_check", "kpi_monitor")
    graph.add_edge("kpi_monitor", "publish")
    graph.set_finish_point("publish")

    result = graph.compile().invoke(state)
    selected = result.get("selected_agents") or []
    print(f"tick: agents={','.join(selected) if selected else '-'}")
    return (
        {"ts": result.get("ts"), "selected_agents": selected, "log": result.get("log", [])},
        int(result.get("kpi_last_run", kpi_last_run)),
        int(result.get("release_cycle_last_run", release_cycle_last_run)),
    )
