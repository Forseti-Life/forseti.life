# Finance Source Map

Use this file to define the authoritative system for each finance signal before publishing numbers.

## Source-of-truth table

| Area | Primary source | Secondary check | Minimum cadence | Notes |
| --- | --- | --- | --- | --- |
| AWS infrastructure expense | AWS Billing / Cost Explorer / invoice export | Prior month summary or vendor reconciliation | Monthly | Use invoice totals for closed months; use Cost Explorer for intra-month estimates |
| GitHub expense | GitHub billing and usage views / invoice export | Prior month summary or vendor reconciliation | Monthly | Separate seat/licensing costs from variable Actions or package charges |
| Other vendor expense | Vendor invoice, receipt, or billing portal export | Bank statement or payment confirmation | Monthly | Domains, workspace/email, SaaS tools, contractors, and similar vendors belong here |
| Processor-based income | Payment processor export or payout report | Bank receipt | Monthly | Keep gross sales, processor fees, refunds, and net payout distinct when possible |
| Sponsorship/donation income | Sponsorship platform export or statement | Bank receipt or payout confirmation | Monthly | Record platform fees separately if visible |
| Invoice-based income | Invoice register or accounting export | Payment receipt or bank deposit | Monthly | Track open receivables separately from collected cash |
| Bank cash movement | Bank statement or transaction export | Ledger totals | Monthly | Required when reconciling timing differences between billed, earned, and collected |

## Rules
- A closed-period total should come from a closed-period system artifact, not a transient dashboard view when an invoice/export exists.
- If only a dashboard screenshot or transient view exists, label the number as provisional.
- If a new finance system appears, add it here before treating it as authoritative.

## Recommended systems to add if missing
1. A reliable export path for every income source.
2. A bank statement archive or equivalent payout confirmation trail.
3. An invoice and receivables register if Forseti bills anyone directly.
4. A renewal tracker for recurring commitments and annual services.
5. A monthly summary artifact in `dashboards/finance/` for each closed month.
