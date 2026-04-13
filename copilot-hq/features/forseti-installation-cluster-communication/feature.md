# Feature: forseti-installation-cluster-communication

- Status: ready
- Website: forseti.life
- Module: forseti_cluster (new)
- Release: unscoped
- Owner: pm-forseti
- Project: PROJ-011

## Summary

Forseti installations currently operate as isolated deployments. This feature introduces secure installation-to-installation cluster communication so one Forseti instance can discover, trust, and exchange signed messages with another Forseti instance. The goal is to establish the communication and governance layer first, so future federation features can build on a stable cross-installation contract instead of ad hoc integrations.

The larger vision is a **community resource mesh**: Forseti systems should be able to identify community needs and share commodity resources while preserving local autonomy. The initial focus is **agent expertise** and **institutional-management services**; compute and storage are future-state extensions.

## Goal

Allow any Forseti installation to register trusted peer installations and exchange authenticated cluster messages through a standard message envelope and admin-managed trust model, with initial support for sharing agent capacity and institutional-service coordination.

## Acceptance criteria

- AC-1: Each installation has a stable installation identity and cluster key material that is not stored in public synced config.
- AC-2: Admins can register a peer installation with base URL, installation identifier, and trust material.
- AC-3: A signed health/status endpoint allows one trusted installation to verify reachability and basic peer metadata of another installation.
- AC-4: A signed cluster message endpoint accepts authenticated inbound messages, rejects unknown peers, and logs message receipt/result.
- AC-5: Outbound cluster messages use a standard message envelope with message type, sender installation id, timestamp, nonce, signature, and payload.
- AC-6: Admin UI shows configured peers, last successful handshake, and last message status.
- AC-7: Replay protection and signature validation are enforced so stale or tampered messages are rejected.

## Definition of done

- Core AC pass in a local two-installation test setup.
- Secrets and trust material are stored in active config, environment, or private files rather than public synced config.
- One end-to-end peer handshake and one signed message exchange are demonstrated successfully.

## Notes

- Start with communication and trust primitives, not full data replication.
- Favor Drupal-native HTTP client, route/controller patterns, and queue/logging primitives.
- The first practical use case can be health/status exchange, remote announcement, cross-installation suggestion relay, shared agent capacity, or institutional-service advertisement.
