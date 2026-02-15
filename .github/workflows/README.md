# GitHub Workflows

- deploy.yml: Deploy Forseti to production.
- auto-assign-copilot.yml: Assign copilot to PRs when opened, reopened, or marked ready for review.
- merge-issue-branches-into-testing.yml: On schedule or manual dispatch, merges open PRs where Copilot is either requested as reviewer or assigned; auto-marks draft PRs ready when Copilot is requested; skips PRs without Copilot involvement; pushes testing when merges occur.

Run merge-issue-branches-into-testing manually via GitHub Actions → "Run workflow" when you want to sync ready PR branches into testing immediately.
