# GitHub Workflows

- deploy.yml: Deploy Forseti to production.
- auto-assign-copilot.yml: Assign copilot to PRs when opened, reopened, or marked ready for review.
- merge-issue-branches-into-testing.yml: On schedule or manual dispatch, merge open, non-draft PR heads assigned to copilot into the testing branch and push testing.

Run merge-issue-branches-into-testing manually via GitHub Actions → "Run workflow" when you want to sync ready PR branches into testing immediately.
