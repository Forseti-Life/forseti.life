import json
import shutil
import subprocess
from datetime import datetime, timezone
from pathlib import Path


BOUNDARY_SCRIPT = Path(__file__).resolve().parents[2] / "scripts" / "ceo-release-boundary-health.sh"
JOBHUNTER_DIRECTIVE = Path(__file__).resolve().parents[2] / "scripts" / "jobhunter-empty-release-directive.sh"


def _make_root(tmp_path: Path) -> Path:
    root = tmp_path / "hq"

    teams_dir = root / "org-chart" / "products"
    teams_dir.mkdir(parents=True)
    teams = {
        "teams": [
            {
                "id": "forseti-jobhunter-automation",
                "pm_agent": "pm-jobhunter",
                "qa_agent": "qa-jobhunter",
                "dev_agent": "dev-jobhunter",
                "active": True,
                "release_preflight_enabled": True,
                "coordinated_release_default": True,
                "site": "forseti.life",
                "aliases": ["jobhunter", "forseti-jobhunter-automation"],
                "site_audit": {"drupal_web_root": "/nonexistent/web"},
            }
        ]
    }
    (teams_dir / "product-teams.json").write_text(json.dumps(teams), encoding="utf-8")

    active = root / "tmp" / "release-cycle-active"
    active.mkdir(parents=True)
    today = datetime.now(timezone.utc).strftime("%Y%m%d")
    current = f"{today}-forseti-jobhunter-automation-release-a"
    nxt = f"{today}-forseti-jobhunter-automation-release-b"
    (active / "forseti-jobhunter-automation.release_id").write_text(current + "\n", encoding="utf-8")
    (active / "forseti-jobhunter-automation.next_release_id").write_text(nxt + "\n", encoding="utf-8")

    pm_groom = root / "sessions" / "pm-jobhunter" / "inbox" / f"{today}-groom-{nxt}"
    pm_groom.mkdir(parents=True)
    (pm_groom / "command.md").write_text("# Groom\n", encoding="utf-8")

    scripts_dir = root / "scripts"
    scripts_dir.mkdir(parents=True)
    shutil.copy2(BOUNDARY_SCRIPT, scripts_dir / "ceo-release-boundary-health.sh")
    shutil.copy2(JOBHUNTER_DIRECTIVE, scripts_dir / "jobhunter-empty-release-directive.sh")
    (scripts_dir / "ceo-release-boundary-health.sh").chmod(0o755)
    (scripts_dir / "jobhunter-empty-release-directive.sh").chmod(0o755)

    (root / "features").mkdir(parents=True)
    return root


def test_jobhunter_empty_release_writes_submission_directive(tmp_path):
    root = _make_root(tmp_path)
    result = subprocess.run(
        ["bash", str(root / "scripts" / "ceo-release-boundary-health.sh")],
        cwd=root,
        capture_output=True,
        text=True,
        env={
            "PATH": "/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin",
            "HQ_ROOT_DIR": str(root),
        },
    )
    assert result.returncode == 0, result.stderr

    today = datetime.now(timezone.utc).strftime("%Y%m%d")
    items = list(
        (root / "sessions" / "pm-jobhunter" / "inbox").glob(
            f"*-scope-activate-{today}-forseti-jobhunter-automation-release-a"
        )
    )
    assert len(items) == 1, [p.name for p in items]

    readme = (items[0] / "README.md").read_text(encoding="utf-8")
    assert "Primary KPI" in readme
    assert "submission_count" in readme
    assert "Keith Aumiller" in readme
    assert "Chief Information Officer" in readme
    assert "jobhunter_applications" in readme
