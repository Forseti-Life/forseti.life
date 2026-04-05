- Status: done
- Summary: Fixed `scripts/improvement-round.sh` to guard against stale orchestrator-generated signoff artifacts (GAP-26B-02). The existing release-id gate already checked file presence via `release-signoff-status.sh`; the new guard adds a second check that scans matching `sessions/*/artifacts/release-signoffs/<slug>.md` files for `Signed by: orchestrator` content — if any stale artifact is found, dispatch is skipped and no inbox items are created. Verified with 3 simulation tests: (1) no signoff → SKIP, (2) orchestrator signoff → SKIP, (3) real PM signoffs → inbox items created. Committed `977372dc`.

## Next actions
- `20260405-hq-script-path-migration` — remaining item in queue (stale `/home/keithaumiller` path audit)

## Blockers
- None.

## Needs from CEO
- N/A.

## ROI estimate
- ROI: 8
- Rationale: Premature improvement-round dispatches caused 4-agent fast-exit waste per occurrence; this guard eliminates the class of premature dispatch caused by stale orchestrator signoff artifacts.

---

## Implementation detail

### scripts/improvement-round.sh (modified)

The TOPIC-encoded gate (lines 13–46) was extended as follows:

```
Step 1 (existing): bash scripts/release-signoff-status.sh "$release_id"
  → exits non-zero if any coordinated PM signoff file is missing
  → SKIP if non-zero

Step 2 (new): scan for stale orchestrator artifacts
  find sessions -type f -path "*/artifacts/release-signoffs/${slug}.md"
  | for each: grep "Signed by: orchestrator"
  → SKIP if any match found
```

A `GAP-26B-02` comment was added at the gate entry point so future maintainers understand the intent.

### Tests (all 3 passed)

```bash
# Test 1: no signoff
bash scripts/improvement-round.sh 20260405 improvement-round-20260405-fake-release-test
# → SKIP: release '20260405-fake-release-test' not fully signed off

# Test 2: stale orchestrator signoff
# (created both pm-dungeoncrawler and pm-forseti signoffs with "Signed by: orchestrator")
bash scripts/improvement-round.sh 20260405 improvement-round-20260405-stale-release-test
# → SKIP: stale orchestrator signoff artifact detected: sessions/pm-dungeoncrawler/...

# Test 3: real PM signoffs
# (created both signoffs without orchestrator marker)
bash scripts/improvement-round.sh 20260405 improvement-round-20260405-real-release-test
# → OK: release confirmed signed off by real PM(s); proceeding
# → Created PM+CEO post-release review inbox items
```

### Scope note
The bare-topic daily loop (`improvement-round-loop.sh` → `improvement-round.sh "$day"`) fires with no release-id and is not subject to this gate — it's an intentional daily cadence dispatch (separate from release-specific dispatch). The recurring problem was specifically the release-id-encoded TOPIC path, which is now guarded.

## Commit
- `977372dc` — fix(dev-infra): improvement-round stale-signoff guard (GAP-26B-02)
