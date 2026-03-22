from __future__ import annotations

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
        agents = ceo_agents + other_agents[:other_cap]
        selected = [str(getattr(a, "agent_id", "")) for a in agents]
        s["selected_agents"] = selected
        s["log"].append({"step": "pick_agents", "selected": selected})
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

