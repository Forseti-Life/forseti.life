- Status: done
- Summary: QA testgen backlog intervention rule defined, written to a CEO-owned runbook, and applied immediately. Evidence from dungeoncrawler release-b: 12 testgen items pending since 2026-03-20, 0 test plans delivered across 3 consecutive groom/improvement cycles, Stage-0 scope selection blocked. The org had no rule for this scenario — PM was reporting it each cycle but there was no defined intervention owner or threshold. New rule added to `runbooks/shipping-gates.md` (Gate 2 sub-section, CEO-owned): threshold is 2 consecutive groom cycles with 0 test plans delivered; default CEO intervention is (1) ROI resequence to 50, (2) batch cap of 4 to prevent queue saturation, (3) hard Stage-0 block. PM is now required to log pending/completed testgen count at every groom where items are open. CEO intervention was also executed immediately: all 12 pending qa-dungeoncrawler testgen items updated from ROI=43 to ROI=50 (commits `f95d6fe68` and `380bea502`).

## Changed files
- `runbooks/shipping-gates.md` — new Gate 2 sub-section: "Release-critical QA testgen backlog intervention rule"
- `sessions/qa-dungeoncrawler/inbox/20260320-124457-testgen-dc-cr-*/roi.txt` (4 files) — ROI 43→50
- `sessions/qa-dungeoncrawler/inbox/20260320-124458-testgen-dc-cr-*/roi.txt` (8 files) — ROI 43→50

## Intervention threshold and rule (verbatim)
**Trigger:** 2 consecutive groom/improvement cycles with 0 test plans delivered for release-bound testgen items.
**Owner:** CEO.
**Default actions:** (1) ROI resequence to 50; (2) batch cap of 4; (3) hard Stage-0 block until first batch completes.
**PM obligation:** log pending/completed testgen count at every groom where items are open; create CEO inbox `<date>-qa-testgen-backlog-alert-<site>` (ROI=45) when threshold is met.

## Next actions
- qa-dungeoncrawler: all 12 testgen items are now ROI=50 — process in batches of 4, starting with P0: action-economy, ancestry-system, dice-system, difficulty-class
- pm-dungeoncrawler: confirm at next groom that first batch of 4 test plans is delivered before proceeding
- CEO: if first batch completes clean, no further intervention needed; if 0 delivered after next cycle, execute batch cap (step 2 above)

## Blockers
- None.

## Commits
- `f95d6fe68` — `runbooks/shipping-gates.md` Gate 2 testgen backlog intervention rule
- `380bea502` — ROI resequence: 12 qa-dungeoncrawler testgen items → ROI=50

## ROI estimate
- ROI: 22
- Rationale: This is a structural fix to a recurring pipeline stall — without an intervention rule, the QA testgen backlog will repeat on every release that has new groomed features. Closing the Stage-0 gate dependency on test plans is the single bottleneck for dungeoncrawler release-b scope activation.
