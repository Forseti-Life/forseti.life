# Permissions validation

- Label: forseti-life
- Base URL: https://forseti.life
- Roles run: anon
- Violations: 1
- Config: org-chart/sites/forseti.life/qa-permissions.json

## Violations

| Rule | Role | Source | Status | Path | URL | Expected |
|---|---|---|---:|---|---|---|
| public-core | anon | crawl | 500 | / | https://forseti.life/ | allow |
