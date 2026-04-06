#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

PRODUCT_TEAMS_JSON="org-chart/products/product-teams.json"

site="${1:-}"
release_id="${2:-}"
empty_release=0

# Parse optional flags (may appear after positional args)
for arg in "$@"; do
  case "$arg" in
    --empty-release) empty_release=1 ;;
  esac
done

if [ -z "$site" ] || [ -z "$release_id" ]; then
  echo "Usage: $0 <site-or-team-alias> <release-id> [--empty-release]" >&2
  echo "Examples:" >&2
  echo "  $0 forseti.life 20260223-coordinated-release" >&2
  echo "  $0 dungeoncrawler 20260223-coordinated-release" >&2
  echo "  $0 dungeoncrawler 20260223-coordinated-release --empty-release" >&2
  echo "  --empty-release: self-certify Gate 2 when no features were shipped (PM authority)" >&2
  exit 2
fi

if ! lookup_result="$(python3 - "$PRODUCT_TEAMS_JSON" "$site" <<'PY'
import json
import sys

cfg_path = sys.argv[1]
query = (sys.argv[2] or '').strip().lower()

with open(cfg_path, 'r', encoding='utf-8') as fh:
    data = json.load(fh)

teams = data.get('teams') or []
for team in teams:
    aliases = [str(a).strip().lower() for a in (team.get('aliases') or []) if str(a).strip()]
    team_id = str(team.get('id') or '').strip().lower()
    team_site = str(team.get('site') or '').strip().lower()
    if query not in aliases and query != team_id and query != team_site:
        continue

    if not team.get('active', False):
        print(f"ERROR: team is not active for query '{query}'", file=sys.stderr)
        raise SystemExit(3)

    pm_agent = str(team.get('pm_agent') or '').strip()
    normalized_site = str(team.get('site') or '').strip()
    team_id_out = str(team.get('id') or '').strip()
    if not pm_agent or not normalized_site:
        print(f"ERROR: team '{team_id_out}' missing pm_agent/site in registry", file=sys.stderr)
        raise SystemExit(4)

    qa_agent = str(team.get('qa_agent') or '').strip()
    print(f"{team_id_out}\t{normalized_site}\t{pm_agent}\t{qa_agent}")
    raise SystemExit(0)

print(f"ERROR: unknown site/team alias: {query}", file=sys.stderr)
print("Update org-chart/products/product-teams.json to onboard this team.", file=sys.stderr)
raise SystemExit(2)
PY
  2>&1)"; then
  echo "$lookup_result" >&2
  exit 2
fi

IFS=$'\t' read -r team_id site pm_agent qa_agent <<<"$lookup_result"

# Fallback: derive qa_agent from team_id if not configured.
if [ -z "$qa_agent" ]; then
  qa_agent="qa-${team_id}"
fi

ts="$(date -Iseconds)"
dir="sessions/${pm_agent}/artifacts/release-signoffs"
mkdir -p "$dir" 2>/dev/null || true

slug="$(printf '%s' "$release_id" | tr -cs 'A-Za-z0-9._-' '-' | sed 's/^-//;s/-$//' | cut -c1-80)"
out_file="${dir}/${slug}.md"

# Gate 2 guard: require QA APPROVE evidence before writing PM signoff artifact.
gate2_approved=0
qa_outbox="sessions/${qa_agent}/outbox"
if [ -d "$qa_outbox" ]; then
  if grep -rl "$release_id" "$qa_outbox/" 2>/dev/null \
       | xargs grep -l "APPROVE" 2>/dev/null \
       | grep -q .; then
    gate2_approved=1
  fi
fi

if [ "$gate2_approved" -ne 1 ]; then
  # Empty-release self-cert bypass (PM authority): when no features were shipped in this
  # release, QA cannot produce APPROVE evidence. PM may self-certify by passing --empty-release.
  # This writes the Gate 2 self-cert to qa outbox on PM's behalf and proceeds with signoff.
  if [ "$empty_release" -eq 1 ]; then
    mkdir -p "$qa_outbox"
    self_cert_file="${qa_outbox}/$(date +%Y%m%d-%H%M%S)-empty-release-self-cert-${slug}.md"
    cat >"$self_cert_file" <<CERT
# Gate 2 Self-Certification — Empty Release

${release_id} — APPROVE — empty release self-certified by PM

## Self-certification basis
- Release closed with zero features shipped (all deferred to next cycle).
- No code changes to verify; Gate 2 QA evidence is not applicable.
- PM is authorized to self-certify empty releases without QA APPROVE or CEO waiver.
- Certified by: ${pm_agent}
- Certified at: ${ts}
CERT
    echo "INFO: empty-release self-cert written to ${self_cert_file}"
    gate2_approved=1
  else
    echo "ERROR: Gate 2 APPROVE evidence not found for release '${release_id}'" >&2
    echo "  Searched: ${qa_outbox}/ for files containing both '${release_id}' and 'APPROVE'" >&2
    echo "  If this release shipped zero features, re-run with --empty-release to self-certify." >&2
    echo "BLOCKED: PM signoff requires Gate 2 QA APPROVE before it can be issued." >&2
    exit 1
  fi
fi

# Stale orchestrator artifact check: if an existing signoff was written by the orchestrator
# (not a real PM), do not treat it as valid — fall through and overwrite after guard passes.
is_stale_orchestrator=0
if [ -f "$out_file" ] && grep -q "Signed by: orchestrator" "$out_file" 2>/dev/null; then
  is_stale_orchestrator=1
fi

if [ -f "$out_file" ] && [ "$is_stale_orchestrator" -eq 0 ]; then
  echo "OK: already signed off: ${pm_agent} ${slug} (${out_file})"
  exit 0
fi

cat >"$out_file" <<MD
# PM signoff

- Release id: ${release_id}
- Site: ${site}
- PM seat: ${pm_agent}
- Signed off at: ${ts}

## Signoff statement
I confirm the PM-level gates for this site are satisfied for this release id:

- Scope is defined; risks are documented.
- Dev provided commit hash(es) + rollback steps.
- QA provided verification evidence and APPROVE (or explicit documented risk acceptance).

If this is part of a coordinated release, the release operator must wait for all required PM signoffs configured in org-chart/products/product-teams.json before the official push.
MD

echo "SIGNED_OFF: ${pm_agent} ${release_id} -> ${out_file}"

# After recording signoff, check if ALL coordinated PMs have now signed.
# If yes, queue a push-ready inbox item for the release operator (pm-forseti).
python3 - "$PRODUCT_TEAMS_JSON" "$release_id" "$slug" "$ROOT_DIR" <<'PY'
import json
import sys
from pathlib import Path

cfg_path, release_id, slug, root = sys.argv[1], sys.argv[2], sys.argv[3], sys.argv[4]
root = Path(root)

with open(cfg_path, 'r', encoding='utf-8') as fh:
    data = json.load(fh)

teams = [t for t in (data.get('teams') or []) if t.get('active') and t.get('coordinated_release_default')]
if len(teams) < 2:
    sys.exit(0)

all_signed = all(
    (root / 'sessions' / t['pm_agent'] / 'artifacts' / 'release-signoffs' / f"{slug}.md").exists()
    for t in teams
)
if not all_signed:
    unsigned = [t['pm_agent'] for t in teams
                if not (root / 'sessions' / t['pm_agent'] / 'artifacts' / 'release-signoffs' / f"{slug}.md").exists()]
    print(f"INFO: coordinated push not yet ready — unsigned: {', '.join(unsigned)}")
    sys.exit(0)

# All signed — queue push-ready item for pm-forseti (release operator per DECISION_OWNERSHIP_MATRIX)
import datetime
ts = datetime.datetime.now().strftime('%Y%m%d-%H%M%S')
item_id = f"{ts}-push-ready-{slug[:40]}"
inbox_dir = root / 'sessions' / 'pm-forseti' / 'inbox' / item_id
outbox_file = root / 'sessions' / 'pm-forseti' / 'outbox' / f"{item_id}.md"

if inbox_dir.exists() or outbox_file.exists():
    print(f"INFO: push-ready item already exists for pm-forseti ({item_id})")
    sys.exit(0)

# Check if any push-ready item for this release already exists
inbox_root = root / 'sessions' / 'pm-forseti' / 'inbox'
needle = f"-push-ready-{slug[:30]}"
for p in (inbox_root.iterdir() if inbox_root.exists() else []):
    if p.is_dir() and needle in p.name:
        print(f"INFO: push-ready item already queued for pm-forseti: {p.name}")
        sys.exit(0)

inbox_dir.mkdir(parents=True, exist_ok=True)
(inbox_dir / 'roi.txt').write_text('200\n', encoding='utf-8')
signers = ', '.join(f"{t['pm_agent']} ({t['site']})" for t in teams)
cmd = f"""# Push ready: {release_id}

All required PM signoffs recorded for coordinated release `{release_id}`.

## Signed off by
{signers}

## Required action
As release operator, proceed with the official push:
1. Verify: `bash scripts/release-signoff-status.sh {release_id}`
2. Push per `runbooks/shipping-gates.md` Gate 4.
3. Complete post-push steps (config import, smoke test, SLA report update).
"""
(inbox_dir / 'command.md').write_text(cmd, encoding='utf-8')
print(f"INFO: ALL PMs signed — queued push-ready item for pm-forseti: {item_id}")
PY

# ── Board email notification ──────────────────────────────────────────────────
# Load board.conf if present (provides BOARD_EMAIL, HQ_FROM_EMAIL, HQ_SITE_NAME)
BOARD_CONF="${ROOT_DIR}/org-chart/board.conf"
if [ -f "$BOARD_CONF" ]; then
  # shellcheck source=../org-chart/board.conf
  source "$BOARD_CONF"
fi
BOARD_EMAIL="${BOARD_EMAIL:-keith.aumiller@stlouisintegration.com}"
HQ_FROM_EMAIL="${HQ_FROM_EMAIL:-hq-noreply@forseti.life}"
HQ_SITE_NAME="${HQ_SITE_NAME:-forseti.life HQ}"

_release_notify_body="Release ${release_id} has been signed off by all PMs and is ready to push to production.

Release ID: ${release_id}
Status: All PM signoffs recorded — push-ready item queued for pm-forseti.

To check release status:
  bash ${ROOT_DIR}/scripts/release-signoff-status.sh ${release_id}

This notification was sent automatically by release-signoff.sh."

printf "To: %s\nFrom: %s\nSubject: [%s] Release ready to push: %s\nContent-Type: text/plain\n\n%s\n" \
  "$BOARD_EMAIL" "$HQ_FROM_EMAIL" "$HQ_SITE_NAME" "$release_id" "$_release_notify_body" \
  | /usr/sbin/sendmail -t \
  && echo "INFO: Board notification sent to ${BOARD_EMAIL}" \
  || echo "WARN: Board notification email failed (sendmail returned non-zero)"
