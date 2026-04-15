#!/usr/bin/env bash
# Shared helpers for detecting unresolved merge state in the current repo.

# shellcheck shell=bash

merge_health_reset() {
  MERGE_HEALTH_IN_GIT_REPO=0
  MERGE_HEALTH_HAS_ISSUES=0
  MERGE_HEALTH_MERGE_HEAD=0
  MERGE_HEALTH_UNMERGED_COUNT=0
  MERGE_HEALTH_GIT_DIR=""
  MERGE_HEALTH_SUMMARY=""
  MERGE_HEALTH_UNMERGED_FILES=()
}

merge_health_scan() {
  local repo_root="${1:-.}"
  merge_health_reset

  if ! git -C "$repo_root" rev-parse --git-dir >/dev/null 2>&1; then
    MERGE_HEALTH_SUMMARY="not a git repository"
    return 0
  fi

  MERGE_HEALTH_IN_GIT_REPO=1
  MERGE_HEALTH_GIT_DIR="$(git -C "$repo_root" rev-parse --git-dir 2>/dev/null || echo "")"

  if git -C "$repo_root" rev-parse -q --verify MERGE_HEAD >/dev/null 2>&1; then
    MERGE_HEALTH_MERGE_HEAD=1
    MERGE_HEALTH_HAS_ISSUES=1
  fi

  while IFS= read -r path; do
    [ -n "$path" ] || continue
    MERGE_HEALTH_UNMERGED_FILES+=("$path")
  done < <(git -C "$repo_root" diff --name-only --diff-filter=U 2>/dev/null || true)

  MERGE_HEALTH_UNMERGED_COUNT="${#MERGE_HEALTH_UNMERGED_FILES[@]}"
  if [ "$MERGE_HEALTH_UNMERGED_COUNT" -gt 0 ]; then
    MERGE_HEALTH_HAS_ISSUES=1
  fi

  if [ "$MERGE_HEALTH_HAS_ISSUES" -eq 0 ]; then
    MERGE_HEALTH_SUMMARY="no active merge conflicts or unfinished merge state"
    return 0
  fi

  local summary_parts=()
  if [ "$MERGE_HEALTH_MERGE_HEAD" -eq 1 ]; then
    summary_parts+=("MERGE_HEAD present")
  fi
  if [ "$MERGE_HEALTH_UNMERGED_COUNT" -gt 0 ]; then
    summary_parts+=("${MERGE_HEALTH_UNMERGED_COUNT} unmerged file(s)")
  fi

  local joined=""
  local part
  for part in "${summary_parts[@]}"; do
    if [ -n "$joined" ]; then
      joined+=", "
    fi
    joined+="$part"
  done
  MERGE_HEALTH_SUMMARY="$joined"
}

merge_health_issue_lines() {
  local max_lines="${1:-10}"
  local emitted=0

  if [ "$MERGE_HEALTH_MERGE_HEAD" -eq 1 ]; then
    echo "MERGE_HEAD present: unfinished merge detected"
  fi

  local path
  for path in "${MERGE_HEALTH_UNMERGED_FILES[@]}"; do
    if [ "$emitted" -ge "$max_lines" ]; then
      break
    fi
    echo "Unmerged: $path"
    emitted=$((emitted + 1))
  done

  if [ "$MERGE_HEALTH_UNMERGED_COUNT" -gt "$emitted" ]; then
    echo "Additional unmerged files: $((MERGE_HEALTH_UNMERGED_COUNT - emitted))"
  fi
}
