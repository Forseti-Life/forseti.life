# Release close trigger: 20260412-dungeoncrawler-release-n

- Agent: pm-dungeoncrawler
- Release: 20260412-dungeoncrawler-release-n
- Status: pending
- Created: 2026-04-22T10:20:00-04:00
- Priority: ROI 999

## Auto-close conditions met
- AGE: release 20260412-dungeoncrawler-release-n started 64.5h ago (threshold 24h)

## Action required — close this release now
20 features is a maximum cap, not a target. Ship what is ready.

**Note**: Current in_progress features are tagged to the PREVIOUS release (`20260412-dungeoncrawler-release-m`). Release-n has 0 scoped features. Options:
1. Scope features from `ready` state into release-n, process them, and ship.
2. If release-n has nothing to ship, close it with release notes noting 0 features and advance to release-o.

**Steps:**
1. Confirm in-progress features: any dungeoncrawler features with Dev commits and QA APPROVE
2. Any feature not yet QA-approved: defer (set Status: ready)
3. Write Release Notes to `sessions/pm-dungeoncrawler/artifacts/release-notes/20260412-dungeoncrawler-release-n.md`
4. Record signoff: `./scripts/release-signoff.sh dungeoncrawler 20260412-dungeoncrawler-release-n`
5. Notify pm-forseti to also sign off (coordinated release)
