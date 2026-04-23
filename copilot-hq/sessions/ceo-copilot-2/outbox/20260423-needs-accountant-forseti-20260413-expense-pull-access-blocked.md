- Status: blocked
- Summary: accountant-forseti is blocked on April 2026 expense reporting due to two missing external access grants: AWS IAM user `forseti` lacks `ce:GetCostAndUsage`, and the GitHub token at `/home/ubuntu/github.token` lacks org billing scope for `Forseti-Life`. CEO cannot provision either — both require human/Board action. A prior CEO escalation (`20260422-ceo-escalation-unblock-aws-github-access`) was already dispatched to accountant-forseti's inbox but the underlying access has not been granted. CEO has now created a Board inbox item with explicit step-by-step instructions for all three resolution paths.

## Next actions
- Board grants AWS `ce:GetCostAndUsage` to IAM user `forseti`, OR provides April AWS cost CSV
- Board provides GitHub token with `read:org` billing scope, OR provides April GitHub billing CSV
- Board confirms authoritative income/cash source for April 2026
- After any one of the above: accountant-forseti re-runs expense pull and posts to `dashboards/finance/expense-ledger-2026-04.md`

## Blockers
- AWS IAM user `forseti` missing `ce:GetCostAndUsage` — requires Board IAM console action
- GitHub token lacks org billing scope — requires Board to create/provide new token
- Income/cash source unconfirmed — requires Board decision

## Needs from Board
1. AWS: grant `ce:GetCostAndUsage` to `arn:aws:iam::647731524551:user/forseti`, OR drop April AWS cost CSV to `sessions/accountant-forseti/artifacts/aws-april-2026-costs.csv`
2. GitHub: create PAT with `read:org` scope for `Forseti-Life` and write to `/home/ubuntu/github-billing.token`, OR drop April GitHub billing CSV to `sessions/accountant-forseti/artifacts/github-april-2026-billing.csv`
3. Confirm income/cash source (Stripe, bank statement, PayPal, or other)

## Decision needed
- Will Board grant IAM + GitHub billing access, or provide manual CSV exports as the alternative path for April expense reporting?

## Recommendation
- Grant IAM permission + create GitHub billing token — these are permanent unlocks that make expense reporting self-service for all future months. CSV exports are a fallback if IAM/token provisioning cannot happen soon.

## ROI estimate
- ROI: 60
- Rationale: April expense reporting is blocked but not release-critical. Access grants are reusable across all future monthly reports, making this a higher-leverage investment than one-time CSV exports.
