"""
llm/lib/routing.py — Shared routing resolution for copilot-sessions-hq.

Used by:
  - llm/validate.sh     (inline Python heredocs)
  - llm/runner.py       (direct import)
  - scripts/agent-exec-next.sh  (inline Python heredoc with sys.path injection)

Public API:
  resolve_model_id(agent_id, routing, agents_yaml_path=None) -> str
  resolve_model_file(agent_id, routing, manifest, models_dir) -> Path | None
  load_yaml(path) -> dict
  load_merged_routing(routing_path) -> dict
  resolve_remote_openai_config(routing) -> tuple[str, str]
  parse_agents_yaml(path) -> dict[str, dict]
"""

from __future__ import annotations

import re
from pathlib import Path
from typing import Dict, Optional


def load_yaml(path: Path) -> dict:
    """Load a YAML file. Requires pyyaml."""
    import yaml  # intentional late import — callers handle ImportError
    return yaml.safe_load(path.read_text(encoding="utf-8")) or {}


def load_merged_routing(routing_path: Path) -> dict:
    """
    Load routing.yaml and merge routing.local.yaml on top if it exists.

    routing.local.yaml is gitignored and machine-specific. It wins on every key
    it defines (default, roles, agents) and may also add an ``endpoints`` block
    for remote backends (e.g. remote-openai).
    """
    base = load_yaml(routing_path)
    local_path = routing_path.parent / (routing_path.stem + ".local.yaml")
    if not local_path.exists():
        return base
    local = load_yaml(local_path)
    merged: dict = dict(base)
    if "default" in local:
        merged["default"] = local["default"]
    if "roles" in local:
        merged_roles = dict(merged.get("roles") or {})
        merged_roles.update(local["roles"])
        merged["roles"] = merged_roles
    if "agents" in local:
        merged_agents = dict(merged.get("agents") or {})
        merged_agents.update(local["agents"])
        merged["agents"] = merged_agents
    if "endpoints" in local:
        merged["endpoints"] = local.get("endpoints") or {}
    return merged


def resolve_remote_openai_config(routing: dict) -> tuple:
    """
    Return (base_url, model) for the remote-openai endpoint from routing config.

    Reads routing["endpoints"]["remote-openai"] which is written by
    routing.local.yaml. Falls back to localhost:1234 if not configured.
    """
    endpoint = (routing.get("endpoints") or {}).get("remote-openai") or {}
    base_url = endpoint.get("base_url") or "http://localhost:1234/v1"
    model = endpoint.get("model") or "default"
    return base_url, model


def parse_agents_yaml(path: Path) -> Dict[str, dict]:
    """
    Parse org-chart/agents/agents.yaml into {agent_id: {role, paused}} dict.
    Uses regex line scanning (no yaml dep) to match existing org tooling pattern.
    """
    agents: Dict[str, dict] = {}
    if not path.exists():
        return agents

    cur_id: Optional[str] = None
    cur_role: Optional[str] = None
    cur_paused: bool = False

    for ln in path.read_text(encoding="utf-8", errors="ignore").splitlines():
        m = re.match(r"^\s*-\s+id:\s*(\S+)\s*$", ln)
        if m:
            if cur_id:
                agents[cur_id] = {"role": cur_role, "paused": cur_paused}
            cur_id, cur_role, cur_paused = m.group(1).strip(), None, False
            continue
        if cur_id:
            m = re.match(r"^\s*role:\s*(\S+)\s*$", ln)
            if m:
                cur_role = m.group(1).strip()
                continue
            m = re.match(r"^\s*paused:\s*(true|false)\s*$", ln, re.I)
            if m:
                cur_paused = m.group(1).lower() == "true"

    if cur_id:
        agents[cur_id] = {"role": cur_role, "paused": cur_paused}

    return agents


def resolve_model_id(
    agent_id: str,
    routing: dict,
    agents_yaml_path: Optional[Path] = None,
) -> str:
    """
    Resolve the model ID for an agent.

    Resolution order (first match wins):
      1. routing["agents"][agent_id]
      2. routing["roles"][role]  — role looked up from agents.yaml
      3. routing["default"]
      4. "copilot"  (hard fallback)

    Returns a route string (e.g. "copilot", "bedrock", "phi-3-mini").
    """
    # 1. Per-agent override
    model_id = (routing.get("agents") or {}).get(agent_id)
    if model_id:
        return model_id

    # 2. Role-based
    if agents_yaml_path:
        agents = parse_agents_yaml(agents_yaml_path)
        role = (agents.get(agent_id) or {}).get("role") or ""
        if role:
            model_id = (routing.get("roles") or {}).get(role)
            if model_id:
                return model_id

    # 3. Default
    return routing.get("default") or "copilot"


def resolve_model_file(
    agent_id: str,
    routing: dict,
    manifest: dict,
    models_dir: Path,
    agents_yaml_path: Optional[Path] = None,
) -> Optional[Path]:
    """
    Return the Path to the local model file for agent_id if it exists on disk,
    or None if the route points to a live backend such as Copilot/Bedrock.
    """
    model_id = resolve_model_id(agent_id, routing, agents_yaml_path)
    if not model_id or model_id == "copilot":
        return None

    for m in manifest.get("models", []):
        if m.get("id") == model_id:
            local = models_dir / m.get("filename", "")
            return local if local.exists() else None

    return None
