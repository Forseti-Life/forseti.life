#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SITE_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"
PYTHON_BIN="${PYTHON_BIN:-python3}"

JOBHUNTER_UID="${JOBHUNTER_UID:-1}"
JOBHUNTER_LIMIT="${JOBHUNTER_LIMIT:-10}"
JOBHUNTER_ROUNDS="${JOBHUNTER_ROUNDS:-3}"
JOBHUNTER_QUEUE_TIME_LIMIT="${JOBHUNTER_QUEUE_TIME_LIMIT:-180}"
JOBHUNTER_RETRY_MANUAL="${JOBHUNTER_RETRY_MANUAL:-1}"

INTERVAL_SECONDS="${INTERVAL_SECONDS:-300}"
MAX_RUNS="${MAX_RUNS:-0}"
TARGET_SUBMITTED_INCREASE="${TARGET_SUBMITTED_INCREASE:-0}"
STALL_TICKS_BEFORE_ESCALATE="${STALL_TICKS_BEFORE_ESCALATE:-3}"
ESCALATE_UNKNOWN_BLOCKER="${ESCALATE_UNKNOWN_BLOCKER:-1}"
ESCALATION_LOG_FILE="${ESCALATION_LOG_FILE:-/tmp/jh_cio_growth_escalations.log}"

LOG_FILE="${LOG_FILE:-/var/log/drupal/jh_cio_growth_loop.log}"
if ! mkdir -p "$(dirname "$LOG_FILE")" 2>/dev/null; then
  LOG_FILE="/tmp/jh_cio_growth_loop.log"
fi

parse_submitted_total() {
  local json_input="$1"
  JH_JSON_PAYLOAD="$json_input" "$PYTHON_BIN" -c 'import json, os
raw = os.environ.get("JH_JSON_PAYLOAD", "").strip()
try:
    payload = json.loads(raw)
    print(int(payload.get("submitted_total_for_user", -1)))
except Exception:
    print(-1)
'
}

parse_tick_metrics() {
  local json_input="$1"
  JH_JSON_PAYLOAD="$json_input" "$PYTHON_BIN" -c 'import json, os

def to_int(value, default=0):
    try:
        return int(value)
    except Exception:
        return default

raw = os.environ.get("JH_JSON_PAYLOAD", "").strip()
payload = {}
try:
    payload = json.loads(raw)
except Exception:
    payload = {}

submitted_total = to_int(payload.get("submitted_total_for_user"), -1)
queued_total = to_int(payload.get("queued_total"), 0)
failed_total = to_int(payload.get("failed_total"), 0)
queue_processed_total = to_int(payload.get("queue_processed_total"), 0)
queue_failed_total = to_int(payload.get("queue_failed_total"), 0)

candidates_total = 0
queue_remaining_total = 0
for round_result in payload.get("round_results", []) or []:
    if isinstance(round_result, dict):
        candidates_total += to_int(round_result.get("candidates"), 0)
        queue_remaining_total += to_int(round_result.get("queue_remaining"), 0)

print("|".join([
    str(submitted_total),
    str(queued_total),
    str(failed_total),
    str(queue_processed_total),
    str(queue_failed_total),
    str(candidates_total),
    str(queue_remaining_total),
]))
'
}

classify_blocker() {
  local rc="$1"
  local tick_submissions="$2"
  local queued_total="$3"
  local failed_total="$4"
  local queue_processed_total="$5"
  local queue_failed_total="$6"
  local candidates_total="$7"
  local queue_remaining_total="$8"

if [[ "$rc" -ne 0 ]]; then
    echo "runner_nonzero_exit"
    return
  fi

  if [[ "$tick_submissions" -gt 0 ]]; then
    echo "none"
    return
  fi

  if [[ "$candidates_total" -eq 0 && "$queue_processed_total" -eq 0 ]]; then
    echo "no_eligible_candidates"
    return
  fi

  if [[ "$queue_failed_total" -gt 0 || "$failed_total" -gt 0 ]]; then
    echo "submission_or_queue_failures"
    return
  fi

  if [[ "$queue_remaining_total" -gt 0 && "$queue_processed_total" -eq 0 ]]; then
    echo "queue_stall"
    return
  fi

  if [[ "$queued_total" -eq 0 ]]; then
    echo "no_jobs_queued"
    return
  fi

  echo "unknown_no_growth"
}

emit_escalation() {
  local now="$1"
  local run_count="$2"
  local submitted_total="$3"
  local no_growth_streak="$4"
  local blocker="$5"
  local details="$6"

  local message
  message="[$now] ESCALATE run=$run_count submitted_total=$submitted_total no_growth_streak=$no_growth_streak blocker=$blocker details=\"$details\""
  echo "$message" | tee -a "$LOG_FILE"

  if mkdir -p "$(dirname "$ESCALATION_LOG_FILE")" 2>/dev/null; then
    echo "$message" >> "$ESCALATION_LOG_FILE"
  fi
}

run_once() {
  local retry_arg="--retry-manual"
  if [[ "$JOBHUNTER_RETRY_MANUAL" != "1" ]]; then
    retry_arg="--no-retry-manual"
  fi

  "$PHP_BIN" "$SITE_ROOT/scripts/jobhunter-cio-auto-apply.php" \
    "--uid=${JOBHUNTER_UID}" \
    "--limit=${JOBHUNTER_LIMIT}" \
    "--rounds=${JOBHUNTER_ROUNDS}" \
    "--queue-time-limit=${JOBHUNTER_QUEUE_TIME_LIMIT}" \
    "$retry_arg"
}

run_count=0
baseline_submitted=""
prev_submitted=""
no_growth_streak=0
last_escalated_streak=0

while true; do
  run_count=$((run_count + 1))
  now="$(date '+%Y-%m-%d %H:%M:%S')"

  set +e
  output="$(run_once 2>&1)"
  rc=$?
  set -e

  submitted_total="$(parse_submitted_total "$output")"
  tick_metrics="$(parse_tick_metrics "$output")"
  IFS='|' read -r metric_submitted_total queued_total failed_total queue_processed_total queue_failed_total candidates_total queue_remaining_total <<< "$tick_metrics"

  if [[ "$submitted_total" -lt 0 && "$metric_submitted_total" -ge 0 ]]; then
    submitted_total="$metric_submitted_total"
  fi

  if [[ -z "$baseline_submitted" && "$submitted_total" -ge 0 ]]; then
    baseline_submitted="$submitted_total"
  fi

  increase="N/A"
  if [[ -n "$baseline_submitted" && "$submitted_total" -ge 0 ]]; then
    increase=$((submitted_total - baseline_submitted))
  fi

  tick_submissions="N/A"
  total_increased="N/A"
  blocker="unknown"

  if [[ -n "$prev_submitted" && "$submitted_total" -ge 0 ]]; then
    tick_submissions=$((submitted_total - prev_submitted))
    if [[ "$tick_submissions" -gt 0 ]]; then
      total_increased="yes"
      no_growth_streak=0
      blocker="none"
    else
      total_increased="no"
      no_growth_streak=$((no_growth_streak + 1))
      blocker="$(classify_blocker "$rc" "$tick_submissions" "$queued_total" "$failed_total" "$queue_processed_total" "$queue_failed_total" "$candidates_total" "$queue_remaining_total")"
    fi
  fi

  if [[ "$submitted_total" -ge 0 ]]; then
    prev_submitted="$submitted_total"
  fi

  {
    echo "[$now] run=$run_count rc=$rc submitted_total=$submitted_total increase=$increase tick_submissions=$tick_submissions total_increased=$total_increased blocker=$blocker no_growth_streak=$no_growth_streak queued_total=$queued_total failed_total=$failed_total queue_processed_total=$queue_processed_total queue_failed_total=$queue_failed_total candidates_total=$candidates_total queue_remaining_total=$queue_remaining_total"
    echo "$output"
    echo "---"
  } | tee -a "$LOG_FILE"

  if [[ "$rc" -ne 0 ]]; then
    echo "[$now] warning: run returned non-zero rc=$rc" | tee -a "$LOG_FILE"
  fi

  if [[ "$ESCALATE_UNKNOWN_BLOCKER" == "1" && "$blocker" == "unknown_no_growth" && "$no_growth_streak" -ge "$STALL_TICKS_BEFORE_ESCALATE" && "$no_growth_streak" -gt "$last_escalated_streak" ]]; then
    emit_escalation "$now" "$run_count" "$submitted_total" "$no_growth_streak" "$blocker" "No submission growth and no clear blocker signal from CIO summary metrics"
    last_escalated_streak="$no_growth_streak"
  fi

  if [[ "$TARGET_SUBMITTED_INCREASE" -gt 0 && "$increase" != "N/A" && "$increase" -ge "$TARGET_SUBMITTED_INCREASE" ]]; then
    echo "[$now] target reached: increase=$increase" | tee -a "$LOG_FILE"
    break
  fi

  if [[ "$MAX_RUNS" -gt 0 && "$run_count" -ge "$MAX_RUNS" ]]; then
    echo "[$now] max runs reached: $MAX_RUNS" | tee -a "$LOG_FILE"
    break
  fi

  sleep "$INTERVAL_SECONDS"
done
