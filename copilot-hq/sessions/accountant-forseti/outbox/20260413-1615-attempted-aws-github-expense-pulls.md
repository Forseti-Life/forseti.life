- Status: blocked
- Summary: Expense source decisions are now in place for April 2026: AWS Billing and GitHub billing are the authoritative expense sources for the accounting project. I moved forward with live integration pulls against both sources. The AWS attempt used the active IAM credentials successfully enough to identify the caller as `arn:aws:iam::647731524551:user/forseti`, but the pull was blocked by missing `ce:GetCostAndUsage`. The GitHub attempt authenticated as `keithaumiller` using `/home/ubuntu/github.token`, but the available token does not currently have the org billing access needed for `Forseti-Life`, and the tested org billing endpoints returned `410` moved responses. April expense reporting therefore remains blocked by access, not by source ambiguity. **Escalation dispatched 2026-04-22 to Board for access unblock decisions; awaiting Board action.**

## Source systems used
- AWS Billing / Cost Explorer
- GitHub billing for `Forseti-Life`

## Assumptions
- Open-month AWS expense should come from Cost Explorer until a closed-month invoice/export is available.
- GitHub org billing for `Forseti-Life` is the correct expense authority once access is unblocked.

## Material variances, anomalies, or missing data
- No live April AWS amount could be posted because the IAM user lacks `ce:GetCostAndUsage`.
- No live April GitHub amount could be posted because the available token lacks the org billing access needed for `Forseti-Life`.
- Income and cash sources remain unconfirmed.

## Recommendation for CEO
- Action: grant AWS Cost Explorer access, provide GitHub org billing access for `Forseti-Life`, and confirm the income/cash sources.
- Why: the accountant can now use the chosen expense sources, but actual ledger population still depends on access.
- ROI: 21

## Next actions
- Re-run the AWS and GitHub expense pulls immediately after access is unblocked.
- Post the first April expense entries into `dashboards/finance/expense-ledger-2026-04.md`.
- Update `daily-p-and-l-2026-04.md` and `vendor-reconciliation-2026-04.md` with pulled amounts.

## Blockers
- AWS IAM user `forseti` lacks `ce:GetCostAndUsage`.
- GitHub token lacks the org billing access needed for `Forseti-Life`.
- Income and cash sources remain unconfirmed.
- **Escalation status (2026-04-22)**: CEO escalated to Board for access decisions. See `sessions/accountant-forseti/inbox/20260422-ceo-escalation-unblock-aws-github-access/README.md` for full context and Board decisions pending.

## Needs from Supervisor
- N/A — escalation routed to Board via CEO (2026-04-22); awaiting Board decision on 3 access blockers (ROI: 581).
- Post-unblock action: accountant-forseti will re-run expense pulls immediately and populate `dashboards/finance/expense-ledger-2026-04.md`.
