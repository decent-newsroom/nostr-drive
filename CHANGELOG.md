# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - Unreleased

Initial implementation of the nostr-drive library with coordinate-first design.

### Added
- `Coordinate` value object for type-safe coordinate handling (`kind:pubkey:d-tag`)
  - `Coordinate::parse()` for parsing coordinate strings
  - `Coordinate::equals()` for coordinate comparison
  - Validation of kind range (30000-39999) and pubkey format
- `Drive` domain model (kind 30042)
  - Root folder mounting via coordinate array
  - Title and description fields
  - Coordinate-based identification
- `Folder` domain model (kind 30045)
  - Coordinate-based membership (a-tags only)
  - Title and description fields
  - Entry management with coordinate references
  - Folder nesting support
- `FolderEntry` readonly value object
  - Coordinate-based references
  - Optional hints: relay, last event ID, name
  - `withHints()` method for updating hints
- `DriveService` for Drive CRUD operations
  - `create()` - Create drive with root mounts
  - `get()` - Fetch by coordinate
  - `update()` - Update drive
  - `setRoots()` - Manage root folder mounts
  - `archive()` - Soft delete with status tag
- `FolderService` for Folder CRUD and membership operations
  - `create()` - Create folder with optional initial entries
  - `get()` - Fetch by coordinate
  - `update()` - Update folder
  - `addEntry()` - Add entry by coordinate
  - `removeEntry()` - Remove entry by coordinate
  - `moveEntry()` - Move entry between folders
  - `reorderEntries()` - Reorder by coordinate array
  - `setEntries()` - Replace all entries
  - `archive()` - Soft delete with status tag
- `EventStoreInterface` contract
  - `getLatestByCoordinate()` - Fetch by coordinate
  - `getLatestByCoordinates()` - Batch fetch
  - `getById()` - Fetch by event ID
  - `publish()` - Publish event
- `KindValidator` with allowlist enforcement
  - Allowed kinds: 30040, 30041, 30024, 30023, 30045, 31924, 31923, 31922
  - Supports folder nesting (kind 30045)
- Comprehensive test suite (65 tests, 166 assertions)
  - Domain model tests
  - Service operation tests
  - Validation tests

### Event Format
- Drive events (30042) with:
  - `["d", identifier]` for addressability
  - `["title", title]` for display name
  - `["description", description]` for description
  - `["a", "30045:pubkey:d"]` for root folder mounts (ordered)
- Folder events (30045) with:
  - `["d", identifier]` for addressability
  - `["title", title]` for display name
  - `["description", description]` for description
  - `["a", "kind:pubkey:d", relay, event-hint, name-hint]` for membership (ordered)

### Design Principles
- **Coordinate-first**: Stable references across event replacements
- **a-tag only membership**: Standard Nostr format for addressable events
- **Tag order matters**: Array order defines display order
- **Immutability**: Coordinate and FolderEntry are readonly
- **Type safety**: Coordinate class validates format and kind range

### Documentation
- Comprehensive README with examples
- Event format specifications
- Architecture overview
- Usage examples with coordinates

---

**Note**: This is the initial release. There are no prior versions or migrations.
