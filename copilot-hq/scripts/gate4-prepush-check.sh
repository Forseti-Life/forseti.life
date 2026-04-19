#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

release_id="${1:-}"

if [ -z "$release_id" ]; then
  echo "Usage: $0 <release-id>" >&2
  echo "Example: $0 20260419-forseti-release-g" >&2
  exit 2
fi

fail() {
  echo "ERROR [gate4-prepush] $*" >&2
  exit 1
}

info() {
  echo "INFO  [gate4-prepush] $*"
}

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  fail "not inside a git working tree"
fi

git_dir="$(git rev-parse --git-dir)"

# Integration state must be clean before any release push.
for state_file in MERGE_HEAD REBASE_HEAD CHERRY_PICK_HEAD REVERT_HEAD BISECT_LOG; do
  if [ -e "$git_dir/$state_file" ]; then
    fail "git integration state detected ($state_file). Finish/abort merge|rebase|cherry-pick|revert first"
  fi
done

branch="$(git rev-parse --abbrev-ref HEAD)"
if [ "$branch" != "main" ]; then
  fail "current branch is '$branch' (expected 'main')"
fi

# A release push must represent a fully captured production working tree.
if ! git diff --quiet || ! git diff --cached --quiet; then
  fail "tracked file changes are present. Commit or discard before Gate 4"
fi

untracked_count="$(git ls-files --others --exclude-standard | wc -l | tr -d ' ')"
if [ "${untracked_count:-0}" -gt 0 ]; then
  sample="$(git ls-files --others --exclude-standard | head -20)"
  fail "untracked files detected (${untracked_count}). Add/commit or clean them before Gate 4. Sample:\n${sample}"
fi

if ! git fetch origin --prune >/dev/null 2>&1; then
  fail "could not fetch origin --prune"
fi

upstream="$(git rev-parse --abbrev-ref --symbolic-full-name '@{u}' 2>/dev/null || true)"
if [ -z "$upstream" ]; then
  fail "no upstream configured for main"
fi

read -r behind ahead <<<"$(git rev-list --left-right --count "${upstream}...HEAD")"

if [ "${behind:-0}" -gt 0 ]; then
  fail "local main is behind ${upstream} by ${behind} commit(s). Run pull --rebase before Gate 4"
fi

if [ "${ahead:-0}" -gt 0 ]; then
  fail "local main has ${ahead} unpushed commit(s). Push to origin before Gate 4"
fi

if ! ./scripts/release-signoff-status.sh "$release_id" >/dev/null 2>&1; then
  fail "release-signoff-status is not ready for official push: ${release_id}"
fi

head_sha="$(git rev-parse --short HEAD)"
info "PASS release=${release_id} branch=main head=${head_sha} upstream=${upstream}"
info "Gate 4 pre-push checks complete. Safe to execute coordinated push and post-coordinated handoff."
