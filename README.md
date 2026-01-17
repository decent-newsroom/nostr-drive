# nostr-drive

A framework-agnostic PHP library that implements a filesystem-like hierarchy on Nostr using Drive events (kind:30042) and Folder events (kind:30045). It provides deterministic CRUD for drives and folders, plus membership operations (add/remove/move/reorder) over addressable event coordinates.

## Key Concepts

- **Coordinate-first design**: Folder membership uses `a` tags (coordinates: `kind:pubkey:d-tag`) as the canonical reference for addressable events
- **Stable across updates**: Coordinates remain stable when events are replaced, unlike event IDs
- **Drive mounts roots**: Drive events mount root folders via `a` tags
- **Order matters**: Tag order defines display order (no explicit position field needed)

## Event Format Examples

### Drive Event (kind:30042)

```json
{
  "kind": 30042,
  "pubkey": "author_pubkey",
  "created_at": 1234567890,
  "content": "",
  "tags": [
    ["d", "my-drive"],
    ["title", "My Main Drive"],
    ["description", "Personal files and folders"],
    ["a", "30045:author_pubkey:themes", "wss://relay.example"],
    ["a", "30045:author_pubkey:magazines", "wss://relay.example"],
    ["a", "30045:author_pubkey:calendar", "wss://relay.example"]
  ]
}
```

### Folder Event (kind:30045)

```json
{
  "kind": 30045,
  "pubkey": "author_pubkey",
  "created_at": 1234567891,
  "content": "",
  "tags": [
    ["d", "themes"],
    ["title", "Themes"],
    ["description", "Website themes and templates"],
    ["a", "30045:author_pubkey:themes/default", "wss://relay.example", "", "default"],
    ["a", "30041:author_pubkey:readme", "wss://relay.example", "event_id_hint", "README"],
    ["a", "30024:author_pubkey:article-1", "wss://relay.example"]
  ]
}
```

**Note:** Membership tags use `a` tag format:
- Minimal: `["a", "kind:pubkey:d-tag", "relay-hint"]`
- Extended: `["a", "kind:pubkey:d-tag", "relay-hint", "last-event-id", "name-hint"]`

## Allowed Content Kinds

Folders may contain entries with the following kinds:

- `30040` - Index
- `30041` - AsciiDoc content
- `30024` - Markdown article
- `30023` - Markdown draft
- `30045` - Folder (allows nesting)
- `31924` - Calendar (NIP-52)
- `31923` - Time-based calendar event (NIP-52)
- `31922` - Date-based calendar event (NIP-52)

## Usage Example

```php
use DecentNewsroom\NostrDrive\Domain\Coordinate;
use DecentNewsroom\NostrDrive\Domain\FolderEntry;
use DecentNewsroom\NostrDrive\Service\DriveService;
use DecentNewsroom\NostrDrive\Service\FolderService;

// Create a drive coordinate
$driveCoord = new Coordinate(30042, $pubkey, 'my-drive');

// Create root folder coordinates
$themesCoord = new Coordinate(30045, $pubkey, 'themes');
$magazinesCoord = new Coordinate(30045, $pubkey, 'magazines');

// Create drive with root mounts
$drive = $driveService->create(
    $driveCoord,
    [$themesCoord, $magazinesCoord],
    'My Drive',
    'Personal workspace'
);

// Create a folder
$folder = $folderService->create(
    $themesCoord,
    [],
    'Themes',
    'Website themes'
);

// Add entry to folder
$articleCoord = new Coordinate(30024, $pubkey, 'my-article');
$entry = new FolderEntry($articleCoord, 'wss://relay.example', null, 'My Article');
$folderService->addEntry($themesCoord, $entry);

// Reorder entries
$coord1 = new Coordinate(30024, $pubkey, 'article-1');
$coord2 = new Coordinate(30024, $pubkey, 'article-2');
$folderService->reorderEntries($themesCoord, [$coord2, $coord1]);
```

## Architecture

- **Domain Models**: `Coordinate`, `Drive`, `Folder`, `FolderEntry`
- **Services**: `DriveService`, `FolderService` (CRUD + membership ops)
- **Contracts**: `EventStoreInterface` (adapter for relay communication)
- **Validation**: `KindValidator` (allowlist enforcement)

## Delete vs Archive

Because these are replaceable events, "delete" is not reliably enforceable at the network level. The library provides:

- **Unlink**: Remove membership from parent folder
- **Archive**: Set `["status", "archived"]` tag (optional convention)

For NIP-09 deletions, implement at the integration layer.

## See Also

- [Full Specification](docs/SPECIFICATION.md) - Comprehensive technical spec
- [TODO](docs/0.1.0/TODO.md) - Implementation notes and refactoring guidance

