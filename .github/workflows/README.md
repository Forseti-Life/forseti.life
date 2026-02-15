# GitHub Workflows

- deploy.yml: Deploy Forseti to production.
- auto-assign-copilot.yml: Assign copilot to PRs when opened, reopened, or marked ready for review.
- merge-issue-branches-into-testing.yml: On schedule or manual dispatch, merges open PRs assigned to copilot into testing; if a PR is draft but Copilot is requested as reviewer, it auto-marks ready before merging; skips remaining drafts; pushes testing when merges occur.

Run merge-issue-branches-into-testing manually via GitHub Actions → "Run workflow" when you want to sync ready PR branches into testing immediately.
