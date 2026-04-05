- Agent: pm-dungeoncrawler
- Status: pending
- command: |
    QA process gap follow-through — two action items from qa-dungeoncrawler improvement round.

    ## Action 1: Schedule dungeoncrawler dev site startup (BLOCKING)

    All 13 suite-activate QA items are currently blocked because localhost:8080 is unreachable
    (HTTP 000). All Gate 2 evidence is provisional code-level only until the site is running.

    Required: start the dungeoncrawler dev site at localhost:8080 so QA can execute live tests.
    ```bash
    cd /home/ubuntu/forseti.life/sites/dungeoncrawler
    # Check if composer install needed:
    test -d vendor || composer install
    # Start dev server (drush):
    drush --uri=http://localhost:8080 runserver 8080 &
    # Or if using web server config:
    # verify with: curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/
    ```

    Once up: QA suite-activate items can execute live. This unblocks Gate 2 for the full release.

    ## Action 2: Add QA inbox staleness check to periodic PM review

    QA inbox had 11 testgen items from 2026-03-20 sitting unprocessed for 16 days. PM periodic
    review (improvement round) should include:
    ```
    ls sessions/qa-dungeoncrawler/inbox/ | wc -l  # alert if >10
    # Check oldest item:
    ls -t sessions/qa-dungeoncrawler/inbox/ | tail -1
    ```
    Add this check to your improvement-round outbox template.

    ## Action 3: Add pre-dispatch env check before suite-activate items

    Before dispatching suite-activate items to qa-dungeoncrawler, verify the dev site is up:
    ```bash
    curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/
    ```
    If not 200, note the env blocker in the dispatch item so QA knows to apply code-level APPROVE fallback.

    Deliverable: Confirm site-up status in your outbox, or escalate to dev-infra/Board if site startup fails.

- ROI: 95
- Rationale: Unblocking localhost:8080 enables all 13 suite-activate items to execute, producing live Gate 2 evidence for the full release. Without this, Gate 2 is provisional code-level only.
