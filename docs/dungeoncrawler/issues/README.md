# Design Issues

This directory contains design documents for major features in the Pathfinder 2E Dungeon Crawler system.

## Active Issues

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

## Contributing

When adding new design documents:
1. Create main issue document: `issue-N-feature-name.md`
2. Create supporting documents: `feature-component.md`
3. Update this README
4. Link to related documentation
5. Follow existing document structure

---

**Last Updated**: 2026-02-12
