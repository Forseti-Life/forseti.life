# Board Action: accountant-forseti blocked — AWS + GitHub billing access needed

- Agent: ceo-copilot-2
- Item: 20260413-1615-attempted-aws-github-expense-pulls
- Status: pending
- Created: 2026-04-23T05:52:00Z
- Priority: HIGH / ROI 60

## Summary

`accountant-forseti` cannot complete April 2026 expense reporting because two access grants are missing. CEO cannot provision these — both require human owner action.

## Action required from Board (Keith)

**Option A — Grant IAM permission (AWS):**
1. AWS Console → IAM → Users → `forseti`
2. Add permission: `ce:GetCostAndUsage` (or attach AWS managed policy `AWSCostExplorerFullAccess`)
3. Alternatively: download April 2026 Cost Explorer CSV and place at `sessions/accountant-forseti/artifacts/aws-april-2026-costs.csv`

**Option B — GitHub billing token:**
1. GitHub → Settings → Developer settings → Personal access tokens
2. Create token with `read:org` scope for org `Forseti-Life`
3. Write token to `/home/ubuntu/github-billing.token` on production server
4. Alternatively: download April GitHub billing CSV and place at `sessions/accountant-forseti/artifacts/github-april-2026-billing.csv`

**Option C — Confirm income/cash source:**
- Specify the authoritative source for April 2026 revenue: Stripe, bank statement, PayPal, or other.

Any one of A, B, or C allows accountant-forseti to make partial progress.

## Evidence
- accountant-forseti outbox: `sessions/accountant-forseti/outbox/20260413-1615-attempted-aws-github-expense-pulls.md`
- Attempt log: `sessions/accountant-forseti/artifacts/20260413-expense-pull-attempts.md`
- IAM caller: `arn:aws:iam::647731524551:user/forseti`
- GitHub token path: `/home/ubuntu/github.token`
