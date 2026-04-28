# Nostr Drive PHP Library

Framework-agnostic PHP library for a Nostr filesystem-like hierarchy using Drive events (`kind:30042`) and Folder events (`kind:30045`).

The library is coordinate-first:

- folder membership is represented by ordered `a` tags
- replaceable/addressable content is linked via `kind:pubkey:d` coordinates
- mutating operations publish a new event for the same coordinate

## Requirements

- PHP 8.2+
- Composer

## Installation

```bash
composer require decent-newsroom/nostr-drive
```

## Domain Model

- `Coordinate`: value object for `kind:pubkey:d` identifiers
- `Event`: typed event DTO (`id`, `kind`, `pubkey`, `createdAt`, `tags`, `content`, `sig`)
- `PublishResult`: publish outcome value object
- `Meta`: metadata value object (`title`, `description`)
- `Drive`: drive aggregate (`30042`) with ordered root mounts
- `Folder`: folder aggregate (`30045`) with ordered membership entries
- `FolderEntry`: coordinate + optional relay/event/name hints

## Contract

Implement `EventStoreInterface` to connect to relays:

```php
use DecentNewsroom\NostrDrive\Contract\EventStoreInterface;
use DecentNewsroom\NostrDrive\Domain\Coordinate;
use DecentNewsroom\NostrDrive\Domain\Event;
use DecentNewsroom\NostrDrive\Domain\PublishResult;

final class MyEventStore implements EventStoreInterface
{
    public function getLatestByCoordinate(Coordinate $coordinate): ?Event
    {
        // Fetch and return latest replaceable event for this coordinate.
    }

    public function getLatestByCoordinates(array $coordinates): array
    {
        // Return array<string, Event> keyed by coordinate string.
    }

    public function getById(string $eventId): ?Event
    {
        // Fetch concrete event by id.
    }

    public function publish(Event $event): PublishResult
    {
        // Publish signed event and return outcome.
    }
}
```

## Service Usage

### Drive operations

```php
use DecentNewsroom\NostrDrive\Domain\Coordinate;
use DecentNewsroom\NostrDrive\Domain\Drive;
use DecentNewsroom\NostrDrive\Domain\Meta;
use DecentNewsroom\NostrDrive\Service\DriveService;

$driveService = new DriveService($eventStore);

$driveCoord = new Coordinate(Drive::KIND, $pubkey, 'my-drive');
$themes = new Coordinate(30045, $pubkey, 'themes');
$magazines = new Coordinate(30045, $pubkey, 'magazines');

// create() publishes and returns Event
$driveEvent = $driveService->create(
    $driveCoord,
    [$themes, $magazines],
    new Meta('My Drive', 'Personal workspace')
);

// get() returns Drive domain object
$drive = $driveService->get($driveCoord);

// setRoots() publishes and returns Event
$updatedDriveEvent = $driveService->setRoots($driveCoord, [$themes]);
```

### Folder operations

```php
use DecentNewsroom\NostrDrive\Domain\Coordinate;
use DecentNewsroom\NostrDrive\Domain\Folder;
use DecentNewsroom\NostrDrive\Domain\FolderEntry;
use DecentNewsroom\NostrDrive\Domain\Meta;
use DecentNewsroom\NostrDrive\Service\FolderService;

$folderService = new FolderService($eventStore);

$folderCoord = new Coordinate(Folder::KIND, $pubkey, 'themes');
$articleCoord = new Coordinate(30024, $pubkey, 'my-article');

// create() publishes and returns Event
$folderEvent = $folderService->create(
    $folderCoord,
    [],
    new Meta('Themes', 'Theme collection')
);

// add() publishes and returns Event
$addEvent = $folderService->add(
    $folderCoord,
    new FolderEntry($articleCoord, 'wss://relay.example', null, 'My Article')
);

// remove() publishes and returns Event
$removeEvent = $folderService->remove($folderCoord, $articleCoord);

// reorder() publishes and returns Event
$coord1 = new Coordinate(30024, $pubkey, 'article-1');
$coord2 = new Coordinate(30024, $pubkey, 'article-2');
$reorderEvent = $folderService->reorder($folderCoord, [$coord2, $coord1]);
```

## Membership and Tag Shape

Folder membership is `a`-tag only.

- Minimal: `["a", "kind:pubkey:d"]`
- With relay hint: `["a", "kind:pubkey:d", "relay"]`
- Extended hints: `["a", "kind:pubkey:d", "relay", "last-seen-event-id", "name-hint"]`

Trailing optional fields may be omitted when empty. Tag order is the default display order.

## Event Structure

### Drive Event (`kind:30042`)

```json
{
  "kind": 30042,
  "pubkey": "author_pubkey",
  "created_at": 1234567890,
  "content": "",
  "tags": [
    ["d", "drive-identifier"],
    ["title", "My Drive"],
    ["description", "Personal files and folders"],
    ["a", "30045:author_pubkey:themes", "wss://relay.example"]
  ]
}
```

### Folder Event (`kind:30045`)

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
    ["a", "30024:author_pubkey:article-1", "wss://relay.example"]
  ]
}
```

## Allowed kinds

`KindValidator` allowlist:

- `30040`, `30041`, `30024`, `30023`, `30045`, `31924`, `31923`, `31922`

## Testing

```bash
composer install
vendor/bin/phpunit
```

## License

MIT
