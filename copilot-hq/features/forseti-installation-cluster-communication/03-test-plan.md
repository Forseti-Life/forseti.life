# Test Plan — forseti-installation-cluster-communication

## Setup

- Two local Forseti installations with distinct base URLs and installation identities
- Trust/bootstrap material configured outside synced public config

## Tests

1. Register a peer installation and complete a successful handshake.
2. Send a valid signed cluster message and confirm successful receipt/logging.
3. Send a message with an invalid signature and confirm rejection.
4. Replay a previously accepted message and confirm replay protection.
5. Disable a peer and confirm outbound and inbound requests are blocked.
6. Verify admin UI shows peer health, last handshake, and recent message outcomes.
