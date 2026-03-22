#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

usage() {
  cat <<'USAGE'
Usage:
  ./scripts/copilot-prompt-loop.sh [session-name]

Interactive commands:
  :help        Show this help
  :show        Show current session info
  :session ID  Switch session name
  :mode MODE   Set mode (chat|suggest|explain) in plugin CLI mode
  :exit        Quit

Environment overrides:
  COPILOT_BIN               Path to copilot executable
  COPILOT_MODEL             Default: gpt-5.3-codex (chat-capable CLI only)
  COPILOT_REQUIRE_AGENTIC   Default: 1 (set 0 to allow non-agentic fallback)
  COPILOT_REQUIRE_MODEL     Default: 1 (set 0 to allow default model fallback)
  COPILOT_SESSION_NAMESPACE Default: manual (set to hq only if you explicitly
                            want this loop to share HQ agent session IDs)
  COPILOT_TIMEOUT_SEC       Default: 900
  COPILOT_TIMEOUT_KILL_SEC  Default: 10
  COPILOT_LOOP_LOG          Default: 1 (set to 0 to disable repo transcript log)
USAGE
}

export PATH="$HOME/.npm-global/bin:$HOME/.local/bin:/usr/local/bin:/usr/bin:/bin:${PATH:-}"
COPILOT_BIN="${COPILOT_BIN:-$(command -v copilot 2>/dev/null || true)}"
if [ -z "$COPILOT_BIN" ] && [ -x "$HOME/.npm-global/bin/copilot" ]; then
  COPILOT_BIN="$HOME/.npm-global/bin/copilot"
fi
if [ -z "$COPILOT_BIN" ]; then
  echo "ERROR: copilot CLI not found in PATH." >&2
  echo "Install or expose it first, then re-run this script." >&2
  exit 1
fi

SESSION_NAME="${1:-interactive-loop}"
SESSION_FILE=""
SESSION_ID=""
LOG_DIR="inbox/responses/copilot-prompt-loop"
LOG_FILE=""
CLI_MODE=""
PROMPT_MODE="chat"
COPILOT_SUPPORTS_MODEL=0
CHAT_MODEL="${COPILOT_MODEL:-gpt-5.3-codex}"
SESSION_NAMESPACE=""

sanitize_name() {
  printf '%s' "$1" | tr -cs 'A-Za-z0-9._-' '-'
}

SESSION_NAMESPACE="$(sanitize_name "${COPILOT_SESSION_NAMESPACE:-manual}")"
if [ -z "$SESSION_NAMESPACE" ]; then
  SESSION_NAMESPACE="manual"
fi

refresh_session() {
  local raw_name="$1"
  SESSION_NAME="$(sanitize_name "$raw_name")"
  [ -n "$SESSION_NAME" ] || SESSION_NAME="interactive-loop"

  SESSION_FILE="$HOME/.copilot/wrappers/${SESSION_NAMESPACE}-${SESSION_NAME}.session"
  mkdir -p "$(dirname "$SESSION_FILE")"
  if [ ! -f "$SESSION_FILE" ] || [ -z "$(head -n1 "$SESSION_FILE" | tr -d ' \t\r\n')" ]; then
    if command -v uuidgen >/dev/null 2>&1; then
      uuidgen > "$SESSION_FILE"
    else
      python3 - <<'PY' > "$SESSION_FILE"
import uuid
print(uuid.uuid4())
PY
    fi
  fi
  SESSION_ID="$(head -n1 "$SESSION_FILE" | tr -d ' \t\r\n')"

  mkdir -p "$LOG_DIR"
  LOG_FILE="$LOG_DIR/hq-${SESSION_NAME}.md"
}

append_log() {
  [ "${COPILOT_LOOP_LOG:-1}" = "1" ] || return 0
  local role="$1"
  local body="$2"
  {
    echo ""
    echo "## ${role} $(date -Iseconds)"
    echo ""
    echo "$body"
  } >> "$LOG_FILE"
}

detect_cli_mode() {
  local help
  help="$($COPILOT_BIN --help 2>&1 || true)"

  if printf '%s' "$help" | grep -q -- '--resume'; then
    CLI_MODE="chat"
    PROMPT_MODE="chat"
    if printf '%s' "$help" | grep -q -- '--model'; then
      COPILOT_SUPPORTS_MODEL=1
    else
      COPILOT_SUPPORTS_MODEL=0
    fi
    return 0
  fi

  if printf '%s' "$help" | grep -qE 'what-the-shell|git-assist|gh-assist'; then
    CLI_MODE="legacy"
    PROMPT_MODE="shell"
    COPILOT_SUPPORTS_MODEL=0
    return 0
  fi

  if printf '%s' "$help" | grep -qE 'suggest[[:space:]]+|explain[[:space:]]+'; then
    CLI_MODE="plugin"
    PROMPT_MODE="suggest"
    COPILOT_SUPPORTS_MODEL=0
    return 0
  fi

  CLI_MODE="unknown"
  PROMPT_MODE="chat"
  COPILOT_SUPPORTS_MODEL=0
}

refresh_session "$SESSION_NAME"
detect_cli_mode

if [ "${COPILOT_REQUIRE_AGENTIC:-1}" = "1" ] && [ "$CLI_MODE" != "chat" ]; then
  echo "ERROR: agentic mode requires a chat-capable Copilot CLI (supports --resume, -p, --allow-all)." >&2
  echo "Found mode: $CLI_MODE using $COPILOT_BIN" >&2
  echo "Set COPILOT_REQUIRE_AGENTIC=0 to allow legacy fallback mode." >&2
  exit 2
fi

if [ "$CLI_MODE" = "chat" ] && [ "${COPILOT_REQUIRE_MODEL:-1}" = "1" ] && [ "$COPILOT_SUPPORTS_MODEL" != "1" ]; then
  echo "ERROR: this chat-capable Copilot CLI does not expose --model; cannot enforce COPILOT_MODEL=$CHAT_MODEL." >&2
  echo "Set COPILOT_REQUIRE_MODEL=0 to use the CLI default model." >&2
  exit 2
fi

run_prompt() {
  local prompt="$1"
  local model_args=()

  if [ "$CLI_MODE" = "legacy" ]; then
    case "$PROMPT_MODE" in
      shell) "$COPILOT_BIN" what-the-shell "$prompt" ;;
      git) "$COPILOT_BIN" git-assist "$prompt" ;;
      gh) "$COPILOT_BIN" gh-assist "$prompt" ;;
      *) "$COPILOT_BIN" what-the-shell "$prompt" ;;
    esac
    return 0
  fi

  if [ "$CLI_MODE" = "plugin" ]; then
    if [ "$PROMPT_MODE" = "explain" ]; then
      "$COPILOT_BIN" explain "$prompt"
    else
      "$COPILOT_BIN" suggest -t "${COPILOT_SUGGEST_TARGET:-shell}" "$prompt"
    fi
    return 0
  fi

  if [ "$CLI_MODE" = "chat" ]; then
    if [ "$COPILOT_SUPPORTS_MODEL" = "1" ] && [ -n "$CHAT_MODEL" ]; then
      model_args=(--model "$CHAT_MODEL")
    fi
    if command -v timeout >/dev/null 2>&1; then
      timeout -k "${COPILOT_TIMEOUT_KILL_SEC:-10}" "${COPILOT_TIMEOUT_SEC:-900}" \
        "$COPILOT_BIN" --resume "$SESSION_ID" --silent --allow-all "${model_args[@]}" -p "$prompt"
    else
      "$COPILOT_BIN" --resume "$SESSION_ID" --silent --allow-all "${model_args[@]}" -p "$prompt"
    fi
    return 0
  fi

  echo "ERROR: unsupported copilot CLI mode; expected chat (--resume) or plugin (suggest/explain)." >&2
  return 1
}

echo "Copilot prompt loop"
echo "Session name: $SESSION_NAME"
echo "Session file: $SESSION_FILE"
echo "Session id:   $SESSION_ID"
echo "Namespace:    $SESSION_NAMESPACE"
echo "Transcript:   $LOG_FILE"
echo "Copilot bin:  $COPILOT_BIN"
echo "CLI mode:     $CLI_MODE"
if [ "$CLI_MODE" = "chat" ]; then
  echo "Agentic:      enabled (--allow-all)"
  if [ "$COPILOT_SUPPORTS_MODEL" = "1" ]; then
    echo "Model:        $CHAT_MODEL"
  else
    echo "Model:        default (this copilot binary does not expose --model)"
  fi
fi
if [ "$CLI_MODE" = "plugin" ]; then
  echo "Prompt mode:  $PROMPT_MODE"
  echo "Note: plugin mode does not support chat session resume; transcript logging remains persistent."
fi
if [ "$CLI_MODE" = "legacy" ]; then
  echo "Prompt mode:  $PROMPT_MODE"
  echo "Note: legacy mode does not support agentic chat sessions or explicit model selection."
fi
echo "Type :help for commands."

while true; do
  if [ -t 1 ]; then
    printf 'copilot> '
  fi

  if ! IFS= read -r line; then
    echo
    exit 0
  fi

  line="${line:-}"
  [ -n "$line" ] || continue

  case "$line" in
    :exit|:quit)
      exit 0
      ;;
    :help)
      usage
      continue
      ;;
    :show)
      echo "Session name: $SESSION_NAME"
      echo "Session file: $SESSION_FILE"
      echo "Session id:   $SESSION_ID"
      echo "Namespace:    $SESSION_NAMESPACE"
      echo "Transcript:   $LOG_FILE"
      echo "Copilot bin:  $COPILOT_BIN"
      echo "CLI mode:     $CLI_MODE"
      if [ "$CLI_MODE" = "plugin" ]; then
        echo "Prompt mode:  $PROMPT_MODE"
      fi
      if [ "$CLI_MODE" = "legacy" ]; then
        echo "Prompt mode:  $PROMPT_MODE"
      fi
      if [ "$CLI_MODE" = "chat" ]; then
        echo "Agentic:      enabled (--allow-all)"
        if [ "$COPILOT_SUPPORTS_MODEL" = "1" ]; then
          echo "Model:        $CHAT_MODEL"
        else
          echo "Model:        default (this copilot binary does not expose --model)"
        fi
      fi
      continue
      ;;
    :session\ *)
      refresh_session "${line#:session }"
      echo "Session set: $SESSION_NAME"
      echo "Session file: $SESSION_FILE"
      echo "Session id:   $SESSION_ID"
      echo "Namespace:    $SESSION_NAMESPACE"
      echo "Transcript:   $LOG_FILE"
      echo "Copilot bin:  $COPILOT_BIN"
      echo "CLI mode:     $CLI_MODE"
      if [ "$CLI_MODE" = "plugin" ]; then
        echo "Prompt mode:  $PROMPT_MODE"
      fi
      if [ "$CLI_MODE" = "legacy" ]; then
        echo "Prompt mode:  $PROMPT_MODE"
      fi
      if [ "$CLI_MODE" = "chat" ]; then
        echo "Agentic:      enabled (--allow-all)"
        if [ "$COPILOT_SUPPORTS_MODEL" = "1" ]; then
          echo "Model:        $CHAT_MODEL"
        else
          echo "Model:        default (this copilot binary does not expose --model)"
        fi
      fi
      continue
      ;;
    :mode\ *)
      mode_value="${line#:mode }"
      case "$mode_value" in
        chat)
          if [ "$CLI_MODE" != "chat" ]; then
            echo "chat mode is unavailable with this copilot binary"
          else
            PROMPT_MODE="chat"
            echo "Prompt mode set: $PROMPT_MODE"
          fi
          ;;
        suggest|explain)
          if [ "$CLI_MODE" != "plugin" ]; then
            echo "plugin modes are unavailable with this copilot binary"
          else
            PROMPT_MODE="$mode_value"
            echo "Prompt mode set: $PROMPT_MODE"
          fi
          ;;
        shell|git|gh)
          if [ "$CLI_MODE" != "legacy" ]; then
            echo "legacy modes are unavailable with this copilot binary"
          else
            PROMPT_MODE="$mode_value"
            echo "Prompt mode set: $PROMPT_MODE"
          fi
          ;;
        *)
          echo "usage: :mode <chat|suggest|explain|shell|git|gh>"
          ;;
      esac
      continue
      ;;
  esac

  append_log "User" "$line"

  if ! out="$(run_prompt "$line" 2>&1)"; then
    rc=$?
    echo "[copilot exited with code $rc]"
    echo "$out"
    append_log "Copilot (error $rc)" "$out"
    continue
  fi

  echo "$out"
  append_log "Copilot" "$out"
  echo
done
