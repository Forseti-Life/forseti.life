# Design Issues

This directory contains design documents for major features in the Pathfinder 2E Dungeon Crawler system.

## Active Issues

## Verification Notes (2026-02-18)

This section reflects current implementation coverage in `dungeoncrawler_content`.

### Validity Summary

- **Fully valid/open**: 1 of 6 issue-design documents
- **Partially valid (some implementation exists)**: 4 of 6
- **Mostly stale/resolved as design gap**: 1 of 6

### Per-Issue Status vs Code

1. **Issue #1 (Character Class HP Design)** — **Partially valid**
	- Class-specific HP is now applied through `CharacterManager::getClassHP()` in character save flow.
	- Original schema-loader-driven class lookup remains incomplete (`SchemaLoader::getClassData()` is still TODO/throws).

2. **Issue #2 (Hexmap Rendering Design)** — **Partially valid**
	- Hexmap runtime and ECS-based rendering are implemented.
	- Design-specific storage/services in this document (e.g., `dungeoncrawler_hexmap_*` tables) are not the active implementation path.

3. **Issue #3 (Game Content System Design)** — **Mostly stale as an “unimplemented” claim**
	- Core services and schema tables are implemented (`ContentRegistry`, `ContentQuery`, `ContentGenerator`, campaign content tables).
	- This document is still useful as architecture rationale, but not accurate where it claims no implementation exists.

4. **Issue #4 (Combat & Encounter System Design)** — **Partially valid**
	- Lightweight combat APIs are implemented (`/api/combat/start`, `/end-turn`, `/attack`, `/get`, `/set`, `/end`).
	- Full target-state surface in design docs remains partially implemented.

5. **Issue #4 (Enhanced Character Sheet Design)** — **Partially valid**
	- Character state REST endpoints and service operations exist.
	- WebSocket sync and several advanced flows remain design-target/pending.

6. **Issue #4 (Procedural Dungeon Generation Design)** — **Fully valid/open**
	- Controller/service scaffolding exists, but generation logic and API behavior remain mostly TODO.

### Issue #1: Character Class HP Design
**File**: `issue-1-character-class-hp-design.md`  
**Status**: Design  
**Description**: Character creation and HP calculation system design

### Issue #2: Hexmap Rendering Design
**File**: `issue-2-hexmap-rendering-design.md`  
**Status**: Design  
**Description**: Hexagonal map rendering system for exploration

### Issue #3: Game Content System Design
**File**: `issue-3-game-content-system-design.md`  
**Status**: Design  
**Description**: Game content management and reference data system

### Issue #4: Combat & Encounter System Design ✅
**File**: `issue-4-combat-encounter-system-design.md`  
**Status**: Complete  
**Description**: PF2e-compliant combat and encounter system

**Related Documents**:
- `combat-state-machine.md` - State transitions and lifecycle
- `combat-database-schema.md` - Database tables and relationships
- `combat-engine-service.md` - Service layer pseudocode
- `combat-action-validation.md` - Action validation rules
- `combat-api-endpoints.md` - REST API specification
- `combat-ui-design.md` - Frontend UI design

## Design Document Structure

Each major issue includes:
1. **Main Issue Document**: Overview and requirements
2. **Supporting Documents**: Detailed specifications for each component
3. **Implementation Guide** (when applicable): Step-by-step implementation plan

## Document Status

- ✅ **Complete**: Design finalized, ready for implementation
- 🔨 **In Progress**: Design work ongoing
- 📝 **Draft**: Initial design phase
- 🔄 **Under Review**: Pending review and approval

## Related Documentation

- **Root**: `/docs/dungeoncrawler/README.md` - Main documentation index
- **Mechanics**: `/docs/dungeoncrawler/0X-*.md` - Game mechanics reference
- **Implementation**: `/docs/dungeoncrawler/PR-*.md` - Implementation guides
- **Tracker Audit (2026-02-18)**: `tracker-validity-review-2026-02-18.md` - semantic validity and consolidation plan for active DCC tracker rows
- **Tracker Supersede Map (2026-02-18)**: `tracker-supersede-map-2026-02-18.md` - exact keep-open vs supersede ID mapping for tracker cleanup

## Contributing

When adding new design documents:
1. Create main issue document: `issue-N-feature-name.md`
2. Create supporting documents: `feature-component.md`
3. Update this README
4. Link to related documentation
5. Follow existing document structure

---

**Last Updated**: 2026-02-18
