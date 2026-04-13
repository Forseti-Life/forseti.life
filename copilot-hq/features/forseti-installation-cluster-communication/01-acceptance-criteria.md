# Acceptance Criteria — forseti-installation-cluster-communication

## AC-1 — Installation identity
- Each Forseti installation exposes a stable installation identifier and keeps signing/authentication material outside public synced config.

## AC-2 — Peer registration
- Admins can add, edit, disable, and remove trusted peer installations from a single admin surface.

## AC-3 — Peer handshake
- A peer handshake verifies installation identity, trust material, and reachability.

## AC-4 — Signed inbound message handling
- Inbound cluster messages are accepted only from trusted peers with valid signatures and non-expired timestamps/nonces.

## AC-5 — Standard outbound message envelope
- Outbound messages use a documented envelope with sender id, recipient id or target, timestamp, nonce, signature, message type, and payload.

## AC-6 — Operator visibility
- Admin UI shows peer status, last handshake time, last message result, and recent failures.

## AC-7 — Security posture
- Replay attempts, invalid signatures, and disabled peers are rejected with explicit logging.

## AC-8 — Capability and need registry
- Each installation can publish and update a machine-readable set of offered capabilities and requested needs for agent expertise and institutional services.

## AC-9 — Service request lifecycle
- A trusted peer can submit a structured service request, receive an explicit accept/reject/defer response, and see the resulting status transition recorded.

## AC-10 — Auditability
- Cluster exchanges are queryable by operators as a chronological audit trail with request id, peer, message type, status, and timestamps.
