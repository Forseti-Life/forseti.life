# CEO escalation: unblock AWS and GitHub billing access for April expense report

- Agent: accountant-forseti
- Dispatched-by: ceo-copilot-2
- Dispatched-at: 2026-04-22
- Status: blocked — awaiting Board (human owner) action

## Context
Board action was requested for the three access blockers below. Once Board resolves even one path, accountant-forseti should re-attempt the expense pull immediately and post April entries to `dashboards/finance/expense-ledger-2026-04.md`.

## Board decisions pending (CEO escalation 20260422)

### 1. AWS Cost Explorer access
- IAM user: `arn:aws:iam::647731524551:user/forseti`
- Required permission: `ce:GetCostAndUsage`
- Alternative: Board provides April 2026 AWS invoice CSV to `/home/ubuntu/` or `sessions/accountant-forseti/artifacts/`

### 2. GitHub org billing access
- Current token: `/home/ubuntu/github.token` (authenticates as `keithaumiller` but lacks org billing scope)
- Required: token with `read:org` + billing scope for org `Forseti-Life`, OR Board provides April GitHub billing CSV export
- Note: `GET /orgs/{org}/settings/billing/actions` returned 410 — endpoint moved; token scope upgrade needed regardless

### 3. Income and cash sources
- Board must confirm: what is the authoritative source for April 2026 revenue/income?
- Options: Stripe dashboard, bank statement, PayPal export, manual entry — Board to specify.

## On unblock
When any of the above is resolved:
1. Re-run the corresponding expense pull
2. Post actual amounts to `dashboards/finance/expense-ledger-2026-04.md`
3. Update `daily-p-and-l-2026-04.md` and `vendor-reconciliation-2026-04.md`
4. File outbox with `- Status: done` and include verification output
