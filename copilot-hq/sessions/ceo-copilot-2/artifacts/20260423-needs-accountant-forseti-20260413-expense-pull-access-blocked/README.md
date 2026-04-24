# CEO Action: accountant-forseti blocked on expense pull access

- Agent: accountant-forseti
- Supervisor: ceo-copilot-2
- Item: 20260413-1615-attempted-aws-github-expense-pulls
- Status: pending
- Created: 2026-04-23T04:45:00Z
- Priority: HIGH / ROI 60

## Issue

`accountant-forseti` is blocked (status: needs-info) on April expense reporting. Two blockers:

1. **AWS**: IAM user `arn:aws:iam::647731524551:user/forseti` is missing `ce:GetCostAndUsage` permission.
2. **GitHub**: Token at `/home/ubuntu/github.token` lacks org billing scope for `Forseti-Life`; org billing endpoints return 410.

## Action required from CEO (→ Board if access grants needed)

1. Grant `ce:GetCostAndUsage` to IAM user `forseti` (or provide a Cost Explorer export).
2. Provide a GitHub personal access token with `read:org` billing scope for `Forseti-Life`.
3. If access cannot be granted, provide a manual expense export (CSV or equivalent) for April 2026.

## Escalation path

This is a Board-level access grant. CEO should forward to Board (Keith) if the IAM/GitHub tokens cannot be provisioned by CEO directly.

## Acceptance criteria

- `accountant-forseti` can run expense pull and produce April 2026 cost report.
- `bash scripts/sla-report.sh` no longer reports BREACH for `accountant-forseti`.
