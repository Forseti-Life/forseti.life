#!/usr/bin/env python3

import json
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[1]
SUITES_ROOT = REPO_ROOT / "qa-suites" / "products"

ALLOWED_TOOLS = {
    "playwright",
    "python",
    "phpunit",
}

REQUIRED_TOP_LEVEL_KEYS = {
    "product_id",
    "product_label",
    "tools",
    "suites",
}

REQUIRED_SUITE_KEYS = {
    "id",
    "label",
    "type",
    "command",
    "artifacts",
    "required_for_release",
}


def fail(msg: str) -> None:
    raise SystemExit(msg)


def load_json(path: Path) -> dict:
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except Exception as e:
        fail(f"ERROR: failed to parse JSON: {path} ({type(e).__name__}: {e})")


def validate_manifest(path: Path) -> None:
    data = load_json(path)

    if not isinstance(data, dict):
        fail(f"ERROR: manifest must be a JSON object: {path}")

    missing = REQUIRED_TOP_LEVEL_KEYS - set(data.keys())
    if missing:
        fail(f"ERROR: missing keys {sorted(missing)} in {path}")

    product_id = data.get("product_id")
    if not isinstance(product_id, str) or not product_id.strip():
        fail(f"ERROR: product_id must be a non-empty string: {path}")

    tools = data.get("tools")
    if not isinstance(tools, list) or not tools:
        fail(f"ERROR: tools must be a non-empty list: {path}")
    for t in tools:
        if not isinstance(t, str) or not t.strip():
            fail(f"ERROR: tools entries must be non-empty strings: {path}")
        if t not in ALLOWED_TOOLS:
            fail(f"ERROR: tool '{t}' not allowed (allowed: {sorted(ALLOWED_TOOLS)}): {path}")

    suites = data.get("suites")
    if not isinstance(suites, list) or not suites:
        fail(f"ERROR: suites must be a non-empty list: {path}")

    seen_ids: set[str] = set()
    for idx, s in enumerate(suites):
        if not isinstance(s, dict):
            fail(f"ERROR: suites[{idx}] must be an object: {path}")
        missing_suite = REQUIRED_SUITE_KEYS - set(s.keys())
        if missing_suite:
            fail(f"ERROR: missing suite keys {sorted(missing_suite)} in {path}")

        sid = s.get("id")
        if not isinstance(sid, str) or not sid.strip():
            fail(f"ERROR: suite id must be a non-empty string in {path}")
        if sid in seen_ids:
            fail(f"ERROR: duplicate suite id '{sid}' in {path}")
        seen_ids.add(sid)

        cmd = s.get("command")
        if not isinstance(cmd, str) or not cmd.strip():
            fail(f"ERROR: suite '{sid}' command must be a non-empty string in {path}")

        arts = s.get("artifacts")
        if not isinstance(arts, list) or not arts:
            fail(f"ERROR: suite '{sid}' artifacts must be a non-empty list in {path}")
        for a in arts:
            if not isinstance(a, str) or not a.strip():
                fail(f"ERROR: suite '{sid}' artifacts entries must be non-empty strings in {path}")

        rfr = s.get("required_for_release")
        if not isinstance(rfr, bool):
            fail(f"ERROR: suite '{sid}' required_for_release must be boolean in {path}")


def main() -> None:
    if not SUITES_ROOT.exists():
        fail(f"ERROR: missing suites root: {SUITES_ROOT}")

    manifests = sorted(SUITES_ROOT.glob("*/suite.json"))
    if not manifests:
        fail(f"ERROR: no manifests found under {SUITES_ROOT}")

    for m in manifests:
        validate_manifest(m)

    print(f"OK: validated {len(manifests)} suite manifest(s)")


if __name__ == "__main__":
    main()
