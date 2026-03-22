#!/usr/bin/env bash
set -euo pipefail

# HQ setup: installs cron-based auto-checkpoint and ensures required dirs.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"
cd "$ROOT_DIR"

mkdir -p inbox/responses inbox/commands inbox/processed

# Install pre-commit hook to run lint-scripts.sh on every commit.
HOOK_FILE=".git/hooks/pre-commit"
if [ -d ".git/hooks" ]; then
cat > "$HOOK_FILE" << 'HOOK'
#!/usr/bin/env bash
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"
bash scripts/lint-scripts.sh
HOOK
chmod +x "$HOOK_FILE"
echo "pre-commit hook installed."
else
  echo "pre-commit hook skipped (.git/hooks not present)."
fi

./scripts/install-cron-auto-checkpoint.sh

echo "HQ setup complete."

# ── Local LLM layer ──────────────────────────────────────────────────────────
# Run this separately on machines with sufficient storage/RAM to enable local
# model inference for QA/BA/explore/security agents:
#
#   ./llm/setup.sh          # Install Python deps + create llm/.venv
#   ./llm/download-models.sh --routing   # Download models referenced in routing.yaml
#   ./llm/validate.sh       # Verify environment
#
# See: runbooks/local-llm-setup.md for full instructions.

# Disable the legacy bash agent executor loop (avoid two orchestrators competing).
./scripts/disable-agent-exec-loop.sh || true

# LangGraph orchestrator loop (authoritative scheduler)
./scripts/install-cron-orchestrator-loop.sh

# Forseti dashboard publisher is run by publish-forseti-agent-tracker-loop
# managed by hq-automation; do not add a separate cron publisher.

# HQ automation converge watchdog (ensures enable/disable flips actually start/stop loops).
./scripts/install-cron-hq-automation.sh
