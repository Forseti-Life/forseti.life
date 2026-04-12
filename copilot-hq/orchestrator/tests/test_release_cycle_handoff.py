"""
Regression tests for release-cycle handoff ordering.

Confirms the orchestrator does not advance the active release on PM signoff
alone; pointer advancement belongs to post-coordinated-push after the actual
deploy handoff completes.
"""

import json
import tempfile
import unittest
from pathlib import Path

import orchestrator.run as run


class TestReleaseCycleHandoff(unittest.TestCase):
    def test_signed_off_release_waits_for_push(self):
        with tempfile.TemporaryDirectory() as td:
            root = Path(td)
            (root / "tmp" / "release-cycle-active").mkdir(parents=True)
            (root / "org-chart" / "products").mkdir(parents=True)
            (root / "sessions" / "pm-dungeoncrawler" / "artifacts" / "release-signoffs").mkdir(parents=True)

            (root / "org-chart" / "products" / "product-teams.json").write_text(
                json.dumps(
                    {
                        "teams": [
                            {
                                "id": "dungeoncrawler",
                                "pm_agent": "pm-dungeoncrawler",
                                "active": True,
                                "release_preflight_enabled": True,
                                "coordinated_release_default": True,
                            }
                        ]
                    }
                ),
                encoding="utf-8",
            )
            (root / "tmp" / "release-cycle-active" / "dungeoncrawler.release_id").write_text(
                "20260412-dungeoncrawler-release-e\n", encoding="utf-8"
            )
            (root / "tmp" / "release-cycle-active" / "dungeoncrawler.next_release_id").write_text(
                "20260412-dungeoncrawler-release-f\n", encoding="utf-8"
            )
            (root / "sessions" / "pm-dungeoncrawler" / "artifacts" / "release-signoffs" / "20260412-dungeoncrawler-release-e.md").write_text(
                "# signoff\n", encoding="utf-8"
            )

            old_root = run.REPO_ROOT
            old_run = run._run
            run.REPO_ROOT = root
            calls = []

            def fake_run(cmd, timeout=0):
                calls.append(cmd)
                return 0, ""

            run._run = fake_run
            try:
                log = []
                run._release_cycle_step(log)
            finally:
                run.REPO_ROOT = old_root
                run._run = old_run

            self.assertEqual(calls, [], "Signed-off release must not auto-advance before push")
            team = log[0]["teams"][0]
            self.assertEqual(team["action"], "signed_off_waiting_push")
            self.assertEqual(team["current"], "20260412-dungeoncrawler-release-e")
            self.assertEqual(team["next"], "20260412-dungeoncrawler-release-f")

    def test_active_release_repairs_stale_next_without_advancing(self):
        with tempfile.TemporaryDirectory() as td:
            root = Path(td)
            (root / "tmp" / "release-cycle-active").mkdir(parents=True)
            (root / "org-chart" / "products").mkdir(parents=True)

            (root / "org-chart" / "products" / "product-teams.json").write_text(
                json.dumps(
                    {
                        "teams": [
                            {
                                "id": "dungeoncrawler",
                                "pm_agent": "pm-dungeoncrawler",
                                "active": True,
                                "release_preflight_enabled": True,
                                "coordinated_release_default": True,
                            }
                        ]
                    }
                ),
                encoding="utf-8",
            )
            (root / "tmp" / "release-cycle-active" / "dungeoncrawler.release_id").write_text(
                "20260412-dungeoncrawler-release-e\n", encoding="utf-8"
            )
            (root / "tmp" / "release-cycle-active" / "dungeoncrawler.next_release_id").write_text(
                "20260412-dungeoncrawler-release-b\n", encoding="utf-8"
            )

            old_root = run.REPO_ROOT
            old_run = run._run
            run.REPO_ROOT = root
            calls = []

            def fake_run(cmd, timeout=0):
                calls.append(cmd)
                return 0, ""

            run._run = fake_run
            try:
                log = []
                run._release_cycle_step(log)
            finally:
                run.REPO_ROOT = old_root
                run._run = old_run

            self.assertEqual(calls, [], "Repairing stale next_release_id must not start a new cycle")
            team = log[0]["teams"][0]
            self.assertEqual(team["action"], "next_fixed")
            self.assertEqual(team["current"], "20260412-dungeoncrawler-release-e")
            self.assertEqual(team["next"], "20260412-dungeoncrawler-release-f")
            self.assertEqual(
                (root / "tmp" / "release-cycle-active" / "dungeoncrawler.next_release_id").read_text(encoding="utf-8").strip(),
                "20260412-dungeoncrawler-release-f",
            )


if __name__ == "__main__":
    unittest.main()
