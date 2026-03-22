- command: |
    Implement inbox-age stagnation detection in scripts/release-kpi-monitor.py.

    Background: The 20260322 improvement rounds identified that high-ROI inbox items can age
    2+ days without triggering any monitoring alert. The EXECUTOR-FAIL flag only surfaces
    executor validation failures, not items that succeed at dispatch but whose throughput
    is too low to land before release gates need them.

    Acceptance criteria:
    1. Add a check to release-kpi-monitor.py: for each agent inbox item with roi.txt >= 10
       that has no outbox counterpart in sessions/<agent>/outbox/, if the item's folder
       mtime is > 24h ago, emit a STALE-INBOX flag in kpi-monitor output.
    2. Format: "STALE-INBOX: <agent>/<item> (roi=<N>, age=<Xh>)" — one line per stale item.
    3. If any STALE-INBOX items exist, the overall stagnation_detected field should be True.
    4. Backward compatible: no changes to existing output keys/fields.
    5. Write a unit test in scripts/tests/test_stale_inbox_detection.py with >= 4 test cases.

    Verification:
    - bash scripts/lint-scripts.sh exits 0
    - python3 -m pytest scripts/tests/test_stale_inbox_detection.py passes all tests
    - python3 -m py_compile scripts/release-kpi-monitor.py exits 0
    - Manual: create a test inbox item with roi.txt=10 older than 24h; confirm STALE-INBOX appears in output

    Owner: dev-infra
    ROI: 10
    Source: 20260322 improvement-round gap review (GAP-C / GAP-C-F)
