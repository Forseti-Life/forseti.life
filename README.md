# Forseti autonomous Drupal development platform monorepo

`forseti.life` is the public monorepo for the Forseti platform: Drupal products, shared modules, platform automation, and supporting documentation for community-managed internet services.

## What is here

- **Product runtimes** for Forseti properties such as the job-hunter experience and DungeonCrawler
- **Shared Drupal modules** and platform services used across those products
- **Automation and orchestration** under `copilot-hq/` for release flow, agent execution, dashboards, and operational tooling
- **Documentation and runbooks** for setup, release management, and platform architecture

## Repository layout

- `sites/` - site-specific Drupal code and configuration
- `shared/` - shared assets and cross-product code
- `copilot-hq/` - autonomous delivery control plane, release orchestration, and dashboards
- `docs/` - product, technical, and operational documentation
- `script/` - setup and verification entrypoints
- `testing/` - validation helpers and test support files

## Quick start

```bash
./script/quick-start.sh
```

First-time environment setup:

```bash
./script/complete-setup.sh
./script/verify-setup.sh
```

## Public repo positioning

This repository is the public platform workspace. Sensitive operational state, private session history, secrets, and host-local runtime artifacts are intentionally excluded from publication.

If you are looking for smaller focused packages, see the extracted companion repositories in the `Forseti-Life` organization, including:

- `forseti-ai-conversation`
- `dungeoncrawler-tester`

## Contributing

Please read:

- [CONTRIBUTING.md](CONTRIBUTING.md)
- [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md)
- [SECURITY.md](SECURITY.md)

## License

Licensed under the [Apache License 2.0](LICENSE).
