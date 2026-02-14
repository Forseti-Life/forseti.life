# Campaign/Dungeon Runtime API Documentation

## Overview

This document describes the campaign/dungeon runtime APIs for managing game state, entities, and visibility rules.

## Authentication & Authorization

All endpoints require:
- User authentication (Drupal session)
- Permission: `access dungeoncrawler characters`
- Campaign ownership validation (enforced via `CampaignAccessCheck`)

Non-owners receive **403 Forbidden** responses.

---

## Campaign State Endpoints

### GET `/api/campaign/{campaignId}/state`

Retrieve current campaign state with optimistic locking version.

**Response:**
```json
{
  "success": true,
  "data": {
    "campaignId": "123",
    "state": {
      "created_by": 1,
      "started": true,
      "progress": [
        {"type": "dungeon_entered", "timestamp": 1234567890}
      ],
      "active_hex": "q0r0",
      "metadata": {}
    },
    "version": 42,
    "updatedAt": "2026-02-14T05:00:00+00:00"
  },
  "version": 42
}
```

**Error Responses:**
- `403` - Access denied to campaign
- `404` - Campaign not found

---

### POST `/api/campaign/{campaignId}/state`

Update campaign state with optimistic locking.

**Request Body:**
```json
{
  "expectedVersion": 42,
  "state": {
    "created_by": 1,
    "started": true,
    "progress": [
      {"type": "dungeon_entered", "timestamp": 1234567890},
      {"type": "room_cleared", "timestamp": 1234567900}
    ],
    "active_hex": "q1r1"
  }
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "campaignId": "123",
    "state": { ... },
    "version": 43,
    "updatedAt": "2026-02-14T05:01:00+00:00"
  },
  "version": 43
}
```

**Error Responses:**
- `400` - Invalid JSON, missing state payload, or schema validation failure
- `403` - Access denied to campaign
- `409` - Version conflict (returns current version and state)

**Schema Validation:**
The state payload is validated against `campaign.schema.json`:
- Required fields: `created_by`, `started`, `progress`
- `created_by` must be integer ≥ 1
- `started` must be boolean
- `progress` must be array of event objects with `type` and `timestamp`

---

## Dungeon State Endpoints

### GET `/api/dungeon/{dungeonId}/state?campaignId={campaignId}`

Retrieve dungeon state for a campaign.

**Query Parameters:**
- `campaignId` (required): Campaign ID

**Response:**
```json
{
  "success": true,
  "data": {
    "dungeonId": "dungeon-001",
    "campaignId": 123,
    "name": "The Forgotten Catacombs",
    "description": "Ancient underground complex",
    "theme": "undead",
    "state": {
      "exploredRooms": ["room-1", "room-2"],
      "currentRoom": "room-2"
    },
    "version": 15,
    "updatedAt": "2026-02-14T05:00:00+00:00"
  },
  "version": 15
}
```

**Error Responses:**
- `400` - Missing campaignId
- `403` - Access denied to campaign
- `404` - Dungeon not found

---

### POST `/api/dungeon/{dungeonId}/state`

Update dungeon state.

**Request Body:**
```json
{
  "campaignId": 123,
  "expectedVersion": 15,
  "state": {
    "exploredRooms": ["room-1", "room-2", "room-3"],
    "currentRoom": "room-3",
    "dungeonId": "dungeon-001"
  }
}
```

**Response:** Same format as GET.

**Error Responses:**
- `400` - Invalid JSON, missing fields, or dungeonId mismatch
- `403` - Access denied to campaign
- `409` - Version conflict

---

## Room State Endpoints

### GET `/api/dungeon/{dungeonId}/room/{roomId}/state?campaignId={campaignId}`

Retrieve room state with visibility and detection filtering applied.

**Response:**
```json
{
  "success": true,
  "data": {
    "campaignId": 123,
    "roomId": "room-3",
    "room": {
      "roomId": "room-3",
      "name": "Guard Chamber",
      "description": "A dimly lit chamber with stone pillars",
      "environmentTags": ["dark", "stone"],
      "layout": {
        "hexes": [
          {"id": "hex-1", "q": 0, "r": 0, "terrain": "stone"}
        ]
      },
      "contents": {
        "objects": [],
        "entities": [
          {
            "instanceId": "goblin-1",
            "type": "npc",
            "characterId": 456,
            "state": {
              "hexId": "hex-1",
              "hp": 8,
              "detected": true
            }
          }
        ]
      }
    },
    "state": {
      "isCleared": false,
      "visibleHexIds": ["hex-1"],
      "dungeonId": "dungeon-001"
    },
    "version": 1234567890,
    "updatedAt": "2026-02-14T05:00:00+00:00"
  }
}
```

**Visibility Rules:**
- Only hexes in `state.visibleHexIds` are returned in `layout.hexes`
- Only entities in visible hexes are included
- Traps are hidden unless `state.detected` is true
- Entities with `hidden: true` are hidden unless `state.detected` is true
- Runtime entities from `dc_campaign_characters` are merged with template contents

**Error Responses:**
- `400` - Missing campaignId or dungeonId mismatch
- `403` - Access denied to campaign
- `404` - Room or room state not found

---

### POST `/api/dungeon/{dungeonId}/room/{roomId}/state`

Update room state.

**Request Body:**
```json
{
  "campaignId": 123,
  "expectedVersion": 1234567890,
  "state": {
    "dungeonId": "dungeon-001",
    "roomId": "room-3",
    "isCleared": true,
    "visibleHexIds": ["hex-1", "hex-2"]
  }
}
```

**Response:** Same format as GET.

**Error Responses:**
- `400` - Invalid JSON, missing fields, or ID mismatch
- `403` - Access denied to campaign
- `404` - Room state not found
- `409` - Version conflict

---

## Entity Lifecycle Endpoints

### POST `/api/campaign/{campaignId}/entity/spawn`

Spawn a new entity instance in the campaign.

**Request Body:**
```json
{
  "type": "npc",
  "instanceId": "goblin-scout-1",
  "characterId": 456,
  "locationType": "room",
  "locationRef": "room-3",
  "stateData": {
    "hexId": "hex-5",
    "hp": 8,
    "maxHp": 8,
    "detected": false,
    "hidden": true
  }
}
```

**Parameters:**
- `type` (required): Entity type - `npc`, `obstacle`, `trap`, `hazard`, or `pc`
- `instanceId` (required): Unique instance identifier (scoped to campaign)
- `characterId` (optional): Reference to character library ID (for pc/npc)
- `locationType` (required): Location type - `room`, `dungeon`, or `tavern`
- `locationRef` (required): Location reference (e.g., room ID)
- `stateData` (optional): Entity-specific runtime state

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 789,
    "campaignId": 123,
    "type": "npc",
    "instanceId": "goblin-scout-1",
    "characterId": 456,
    "locationType": "room",
    "locationRef": "room-3",
    "stateData": { ... }
  }
}
```

**Error Responses:**
- `400` - Missing required fields, invalid type, or instanceId already exists
- `403` - Access denied to campaign

---

### POST `/api/campaign/{campaignId}/entity/{instanceId}/move`

Move an entity to a new location.

**Request Body:**
```json
{
  "locationType": "room",
  "locationRef": "room-4"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 789,
    "campaignId": 123,
    "type": "npc",
    "instanceId": "goblin-scout-1",
    "characterId": 456,
    "locationType": "room",
    "locationRef": "room-4",
    "stateData": { ... }
  }
}
```

**Error Responses:**
- `400` - Invalid JSON or missing fields
- `403` - Access denied to campaign
- `404` - Entity not found

---

### DELETE `/api/campaign/{campaignId}/entity/{instanceId}`

Despawn (remove) an entity from the campaign.

**Response:**
```json
{
  "success": true,
  "message": "Entity despawned successfully"
}
```

**Error Responses:**
- `403` - Access denied to campaign
- `404` - Entity not found

---

### GET `/api/campaign/{campaignId}/entities`

List entities in a campaign with optional filtering.

**Query Parameters (all optional):**
- `locationType`: Filter by location type
- `locationRef`: Filter by location reference
- `type`: Filter by entity type

**Example:** `/api/campaign/123/entities?locationType=room&locationRef=room-3`

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 789,
      "campaignId": 123,
      "type": "npc",
      "instanceId": "goblin-scout-1",
      "characterId": 456,
      "locationType": "room",
      "locationRef": "room-3",
      "stateData": { ... }
    }
  ],
  "count": 1
}
```

**Error Responses:**
- `403` - Access denied to campaign

---

## Entity Instance Model

Entity instances are stored in `dc_campaign_characters` table with the following structure:

| Field | Type | Description |
|-------|------|-------------|
| `id` | int | Primary key |
| `campaign_id` | int | Campaign ID |
| `character_id` | int | Library character ID (0 for non-character entities) |
| `instance_id` | string | Unique instance identifier (scoped to campaign) |
| `type` | string | Entity type: `pc`, `npc`, `obstacle`, `trap`, `hazard` |
| `location_type` | string | Current location type: `room`, `dungeon`, `tavern` |
| `location_ref` | string | Location reference (e.g., room ID) |
| `state_data` | JSON | Entity-specific runtime state |
| `created` | timestamp | Creation timestamp |
| `updated` | timestamp | Last update timestamp |

**Entity Types:**
- **pc**: Player character instance
- **npc**: Non-player character (creature, NPC)
- **obstacle**: Physical obstacle (furniture, pillar, etc.)
- **trap**: Trap (hidden by default)
- **hazard**: Environmental hazard

**State Data Examples:**

NPC State:
```json
{
  "hexId": "hex-5",
  "hp": 8,
  "maxHp": 8,
  "conditions": [],
  "initiative": 15,
  "detected": true,
  "hidden": false
}
```

Trap State:
```json
{
  "hexId": "hex-10",
  "detected": false,
  "disarmed": false,
  "triggerType": "pressure_plate",
  "damage": "2d6 piercing"
}
```

---

## Visibility & Detection Rules

### Hex Visibility (Fog of War)
- Room state contains `visibleHexIds` array
- Only hexes in this array are returned in `layout.hexes`
- Entities/objects in non-visible hexes are filtered out

### Entity Detection
- **Traps**: Hidden by default unless `state.detected === true`
- **Hidden Entities**: Entities with `hidden: true` are hidden unless `state.detected === true`
- **NPCs/Obstacles**: Visible if in a visible hex (unless explicitly hidden)

### Example Workflow

1. **Room Entry**: Client requests room state
2. **Server Response**: Returns only visible hexes and detected entities
3. **Perception Check**: Client makes perception check
4. **Spawn Trap**: If successful, client can mark trap as detected by updating entity state
5. **Update Room State**: Client updates room state to mark trap location as visible

---

## Error Response Format

All error responses follow this format:

```json
{
  "success": false,
  "error": "Error message",
  "validation_errors": ["Field 'x' is required"] // (optional, for validation errors)
}
```

**HTTP Status Codes:**
- `400` - Bad Request (invalid payload, validation failure)
- `403` - Forbidden (access denied)
- `404` - Not Found (resource not found)
- `409` - Conflict (version mismatch)
- `500` - Internal Server Error

---

## Examples

### Complete Workflow: Spawning and Moving an NPC

1. **Spawn Goblin in Room 3:**
```bash
POST /api/campaign/123/entity/spawn
{
  "type": "npc",
  "instanceId": "goblin-scout-1",
  "characterId": 456,
  "locationType": "room",
  "locationRef": "room-3",
  "stateData": {
    "hexId": "hex-5",
    "hp": 8,
    "maxHp": 8,
    "detected": false,
    "hidden": true
  }
}
```

2. **Player Succeeds Perception Check, Mark Detected:**

Note: To update entity state, you would typically fetch the entity, modify its stateData, and then use the move endpoint to update location or spawn a new corrected instance. For in-place state updates without location change, you could re-spawn with the same location but updated state, or implement a dedicated PATCH endpoint for state updates.

For this example, we'll update via the entity's stateData by moving it:
```bash
# First, retrieve the entity to get current state
GET /api/campaign/123/entities?instanceId=goblin-scout-1

# Then update by moving to same location with detected flag
# (In practice, you'd implement a PATCH endpoint or update via room state)
# For now, the client would track detection state in room state or re-spawn

# Alternative: Update room state to mark goblin as detected
POST /api/dungeon/dungeon-001/room/room-3/state
{
  "campaignId": 123,
  "state": {
    "dungeonId": "dungeon-001",
    "detectedEntities": ["goblin-scout-1"]
  }
}
```

3. **Goblin Moves to Room 4:**
```bash
POST /api/campaign/123/entity/goblin-scout-1/move
{
  "locationType": "room",
  "locationRef": "room-4"
}
```

4. **Goblin is Defeated, Despawn:**
```bash
DELETE /api/campaign/123/entity/goblin-scout-1
```

---

## Notes

- All timestamps are Unix timestamps (seconds since epoch)
- All JSON responses use camelCase for consistency with frontend
- Campaign state is versioned using optimistic locking
- Entity instances are scoped to campaigns and can be reused across sessions
- Contents data in rooms serves as templates; runtime entities override/extend it
