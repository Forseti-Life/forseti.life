- command: |
    Final full regression gate for release-cycle repair loop.

    - Product/site: forseti.life
    - Triggered by completed Dev findings item: 20260423-clarify-escalation-20260423-191501-qa-findings-forseti-life-1
    - Dev outbox evidence: sessions/dev-forseti/outbox/20260423-clarify-escalation-20260423-191501-qa-findings-forseti-life-1.md

    Required actions:
    1) Run a full regression for this product/site (all required suites + scripted URL/route/permission checks).
    2) Update PASS/FAIL evidence and call out any remaining failures explicitly.
    3) If all required tests PASS, notify PM release coordinator that this product is ready for ship gate.
    4) If any test FAILS, notify Dev with concrete failing items and evidence, and continue the repair loop.

    Deliverable:
    - Outbox report with explicit APPROVE/BLOCK and links to evidence artifacts.
