#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

shopt -s nullglob
files=(inbox/commands/*.md)
shopt -u nullglob

if [ "${#files[@]}" -eq 0 ]; then
  echo "No commands pending."
  exit 0
fi

for f in "${files[@]}"; do
  pm=$(grep -m1 '^\- pm:' "$f" | sed 's/^- pm: *//' || true)
  wi=$(grep -m1 '^\- work_item:' "$f" | sed 's/^- work_item: *//' || true)
  topic=$(grep -m1 '^\- topic:' "$f" | sed 's/^- topic: *//' || true)

  if [ -z "$pm" ] || [ -z "$topic" ]; then
    echo "Skipping (missing pm/topic): $f"
    continue
  fi

  # Create PM work request folder with required templates.
  ./scripts/dispatch-pm-request.sh "$pm" "$wi" "$topic" >/dev/null

  # Attach the command text into the created PM inbox folder.
  latest_dir=$(ls -dt "sessions/${pm}/inbox/"*"-${topic}" 2>/dev/null | head -n 1 || true)
  if [ -n "$latest_dir" ]; then
    cp "$f" "$latest_dir/command.md"
  fi

  mkdir -p inbox/processed
  mv "$f" "inbox/processed/$(basename "$f")"
  echo "Dispatched: $(basename "$f") -> $pm"
done
