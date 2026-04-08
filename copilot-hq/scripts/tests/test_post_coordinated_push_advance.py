"""Tests for post-coordinated-push.sh Step 3: release_id advancement."""
import json
import subprocess
import tempfile
import textwrap
from datetime import datetime, timezone
from pathlib import Path

import pytest

SCRIPT = Path(__file__).resolve().parents[2] / "scripts" / "post-coordinated-push.sh"
assert SCRIPT.exists(), f"Script not found: {SCRIPT}"

_TEAMS_JSON = {
    "teams": [
        {
            "id": "forseti",
            "pm_agent": "pm-forseti",
            "qa_agent": "qa-forseti",
            "dev_agent": "dev-forseti",
            "active": True,
            "coordinated_release_default": True,
            "site": "forseti.life",
            "site_audit": {"drupal_web_root": "/nonexistent/web"},
        },
        {
            "id": "dungeoncrawler",
            "pm_agent": "pm-dungeoncrawler",
            "qa_agent": "qa-dungeoncrawler",
            "dev_agent": "dev-dungeoncrawler",
            "active": True,
            "coordinated_release_default": True,
            "site": "dungeoncrawler.forseti.life",
            "site_audit": {"drupal_web_root": "/nonexistent/web"},
        },
    ]
}


def _make_root(tmp: Path, *, signoffs_done: bool = True) -> Path:
    """Build minimal HQ directory structure in tmp for post-coordinated-push.sh."""
    root = tmp / "hq"

    # product-teams.json
    teams_dir = root / "org-chart" / "products"
    teams_dir.mkdir(parents=True)
    (teams_dir / "product-teams.json").write_text(json.dumps(_TEAMS_JSON))

    # tmp/release-cycle-active/
    active = root / "tmp" / "release-cycle-active"
    active.mkdir(parents=True)

    today = datetime.now(timezone.utc).strftime("%Y%m%d")
    for team_id in ("forseti", "dungeoncrawler"):
        current = f"{today}-{team_id}-release-b"
        nxt = f"{today}-{team_id}-release-c"
        (active / f"{team_id}.release_id").write_text(current + "\n")
        (active / f"{team_id}.next_release_id").write_text(nxt + "\n")
        (active / f"{team_id}.started_at").write_text(datetime.now(timezone.utc).isoformat() + "\n")

        if signoffs_done:
            # Pre-create signoff files so release-signoff.sh is skipped.
            signoff_dir = root / "sessions" / f"pm-{team_id}" / "artifacts" / "release-signoffs"
            signoff_dir.mkdir(parents=True)
            (signoff_dir / f"{current}.md").write_text("## Release Signoff\n")

    # scripts/ symlink so the script can find release-signoff.sh via path discovery.
    # We mock release-signoff.sh with a no-op stub that always exits 0.
    scripts_dir = root / "scripts"
    scripts_dir.mkdir(parents=True)
    stub = scripts_dir / "release-signoff.sh"
    stub.write_text("#!/usr/bin/env bash\nexit 0\n")
    stub.chmod(0o755)

    return root


def _run(root: Path) -> subprocess.CompletedProcess:
    return subprocess.run(
        ["bash", str(SCRIPT)],
        cwd=str(root),
        capture_output=True,
        text=True,
        env={
            "PATH": "/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin",
            "HQ_ROOT_DIR": str(root),
        },
    )


class TestReleaseIdAdvancement:
    def test_release_id_advanced_after_push(self, tmp_path):
        """After first run, release_id files are set to the former next_release_id."""
        root = _make_root(tmp_path)
        today = datetime.now(timezone.utc).strftime("%Y%m%d")
        result = _run(root)
        assert result.returncode == 0, result.stderr

        active = root / "tmp" / "release-cycle-active"
        for team_id in ("forseti", "dungeoncrawler"):
            content = (active / f"{team_id}.release_id").read_text().strip()
            assert content == f"{today}-{team_id}-release-c", (
                f"{team_id}: expected release-c, got {content!r}"
            )

    def test_next_release_id_updated_after_push(self, tmp_path):
        """After first run, next_release_id is set to a new value distinct from the current."""
        root = _make_root(tmp_path)
        today = datetime.now(timezone.utc).strftime("%Y%m%d")
        _run(root)

        active = root / "tmp" / "release-cycle-active"
        for team_id in ("forseti", "dungeoncrawler"):
            current = (active / f"{team_id}.release_id").read_text().strip()
            nxt = (active / f"{team_id}.next_release_id").read_text().strip()
            assert nxt != current, f"{team_id}: next_release_id == release_id ({current!r})"
            assert nxt.startswith(f"{today}-{team_id}-"), f"{team_id}: unexpected next prefix: {nxt!r}"

    def test_idempotent_second_run(self, tmp_path):
        """Second run detects marker already exists and does NOT overwrite release_id files."""
        root = _make_root(tmp_path)
        today = datetime.now(timezone.utc).strftime("%Y%m%d")

        # First run — advances
        _run(root)

        active = root / "tmp" / "release-cycle-active"
        rid_after_first = {}
        for team_id in ("forseti", "dungeoncrawler"):
            rid_after_first[team_id] = (active / f"{team_id}.release_id").read_text().strip()

        # Simulate a new, different next_release_id being written externally
        for team_id in ("forseti", "dungeoncrawler"):
            (active / f"{team_id}.next_release_id").write_text(f"{today}-{team_id}-release-z\n")

        # Second run — should be a no-op for release_id files
        result = _run(root)
        assert result.returncode == 0

        for team_id in ("forseti", "dungeoncrawler"):
            rid_after_second = (active / f"{team_id}.release_id").read_text().strip()
            assert rid_after_second == rid_after_first[team_id], (
                f"{team_id}: idempotency violated — release_id changed on second run"
            )
        assert any(
            f"SKIP {t}: release_id already advanced" in result.stdout
            for t in ("forseti", "dungeoncrawler")
        ), f"Expected SKIP message for idempotent second run; got:\n{result.stdout}"

    def test_missing_next_release_id_file_warns_and_skips(self, tmp_path):
        """If next_release_id file is absent, warns and skips that team but exits 0."""
        root = _make_root(tmp_path)
        today = datetime.now(timezone.utc).strftime("%Y%m%d")
        active = root / "tmp" / "release-cycle-active"

        # Remove next_release_id for dungeoncrawler
        (active / "dungeoncrawler.next_release_id").unlink()

        result = _run(root)
        assert result.returncode == 0
        assert "WARN dungeoncrawler" in result.stdout
        # forseti should still be advanced
        forseti_rid = (active / "forseti.release_id").read_text().strip()
        assert forseti_rid == f"{today}-forseti-release-c"
        # dungeoncrawler stays at release-b
        dc_rid = (active / "dungeoncrawler.release_id").read_text().strip()
        assert dc_rid == f"{today}-dungeoncrawler-release-b"

    def test_started_at_updated(self, tmp_path):
        """started_at file is updated with a recent timestamp after advancement."""
        root = _make_root(tmp_path)
        active = root / "tmp" / "release-cycle-active"

        old_ts = "2020-01-01T00:00:00+00:00"
        for team_id in ("forseti", "dungeoncrawler"):
            (active / f"{team_id}.started_at").write_text(old_ts + "\n")

        _run(root)

        for team_id in ("forseti", "dungeoncrawler"):
            new_ts = (active / f"{team_id}.started_at").read_text().strip()
            assert new_ts != old_ts, f"{team_id}: started_at not updated"
            assert new_ts > "2024", f"{team_id}: started_at appears stale: {new_ts!r}"
