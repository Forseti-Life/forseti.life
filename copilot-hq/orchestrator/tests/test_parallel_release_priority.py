import tempfile
import unittest
from pathlib import Path

import orchestrator.run as run


class TestParallelReleasePriority(unittest.TestCase):
    def test_next_release_agents_fill_spare_capacity_before_other_work(self):
        with tempfile.TemporaryDirectory() as td:
            root = Path(td)
            (root / "org-chart" / "agents").mkdir(parents=True)
            (root / "tmp" / "release-cycle-active").mkdir(parents=True)

            (root / "org-chart" / "agents" / "agents.yaml").write_text(
                "\n".join(
                    [
                        "agents:",
                        "  - id: dev-forseti",
                        "  - id: pm-forseti",
                        "  - id: pm-infra",
                    ]
                )
                + "\n",
                encoding="utf-8",
            )
            (root / "tmp" / "release-cycle-active" / "forseti.release_id").write_text(
                "20260412-forseti-release-l\n", encoding="utf-8"
            )
            (root / "tmp" / "release-cycle-active" / "forseti.next_release_id").write_text(
                "20260412-forseti-release-m\n", encoding="utf-8"
            )

            current_item = root / "sessions" / "dev-forseti" / "inbox" / "20260415-impl-20260412-forseti-release-l"
            current_item.mkdir(parents=True)
            (current_item / "roi.txt").write_text("7\n", encoding="utf-8")

            next_item = root / "sessions" / "pm-forseti" / "inbox" / "20260415-groom-20260412-forseti-release-m"
            next_item.mkdir(parents=True)
            (next_item / "roi.txt").write_text("25\n", encoding="utf-8")

            other_item = root / "sessions" / "pm-infra" / "inbox" / "20260415-ops-cleanup"
            other_item.mkdir(parents=True)
            (other_item / "roi.txt").write_text("999\n", encoding="utf-8")

            old_root = run.REPO_ROOT
            run.REPO_ROOT = root
            try:
                agents = run._prioritized_agents()
            finally:
                run.REPO_ROOT = old_root

            ordered = [agent.agent_id for agent in agents]
            self.assertEqual(ordered, ["dev-forseti", "pm-forseti", "pm-infra"])
            self.assertTrue(agents[0].has_release_work)
            self.assertTrue(agents[1].has_next_release_work)
            self.assertFalse(agents[2].has_release_work)
            self.assertFalse(agents[2].has_next_release_work)


if __name__ == "__main__":
    unittest.main()
