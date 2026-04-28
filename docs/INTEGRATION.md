# Using nostr-drive in Another Project

This guide explains how to add `nostr-drive` as a dependency in your own PHP project, implement the required adapter, and wire the services.

## Requirements

- PHP 8.2+
- Composer

---

## 1. Install

### From Packagist

```bash
composer require decent-newsroom/nostr-drive
```

### From a local path (during development)

Add a `repositories` entry to your project's `composer.json` pointing at the local checkout:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../nostr-drive",
      "options": { "symlink": true }
    }
  ],
  "require": {
    "decent-newsroom/nostr-drive": "*"
  }
}
```

Then run:

```bash
composer install
```

### From a VCS URL (e.g. GitHub)

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/your-org/nostr-drive"
    }
  ],
  "require": {
    "decent-newsroom/nostr-drive": "dev-main"
  }
}
```

---

## 2. Implement `EventStoreInterface`

The library contains **no relay/network code**. You must provide a class that implements
[`EventStoreInterface`](../src/Contract/EventStoreInterface.php) and connects to your
Nostr relay client.

```php
<?php

use DecentNewsroom\NostrDrive\Contract\EventStoreInterface;
use DecentNewsroom\NostrDrive\Domain\Coordinate;
use DecentNewsroom\NostrDrive\Domain\Event;
use DecentNewsroom\NostrDrive\Domain\PublishResult;

final class MyNostrEventStore implements EventStoreInterface
{
    public function __construct(private MyRelayClient $relay) {}

    /**
     * Fetch the latest replaceable event for a coordinate (kind + pubkey + d-tag).
     */
    public function getLatestByCoordinate(Coordinate $coordinate): ?Event
    {
        $raw = $this->relay->queryOne([
            'kinds'   => [$coordinate->getKind()],
            'authors' => [$coordinate->getPubkey()],
            '#d'      => [$coordinate->getIdentifier()],
        ]);

        return $raw ? Event::fromArray($raw) : null;
    }

    /**
     * Fetch the latest events for multiple coordinates.
     * Must return array<string, Event> keyed by coordinate string ("kind:pubkey:d").
     */
    public function getLatestByCoordinates(array $coordinates): array
    {
        $result = [];
        foreach ($coordinates as $coordinate) {
            $event = $this->getLatestByCoordinate($coordinate);
            if ($event !== null) {
                $result[$coordinate->toString()] = $event;
            }
        }
        return $result;
    }

    /**
     * Fetch a concrete event by its ID.
     */
    public function getById(string $eventId): ?Event
    {
        $raw = $this->relay->queryOne(['ids' => [$eventId]]);
        return $raw ? Event::fromArray($raw) : null;
    }

    /**
     * Sign and publish an event to the relay.
     * The Event DTO will NOT have an id/sig yet — your client must add them.
     */
    public function publish(Event $event): PublishResult
    {
        try {
            $this->relay->publish($event->toArray()); // sign + send
            return PublishResult::ok();
        } catch (\Throwable $e) {
            return PublishResult::fail($e->getMessage());
        }
    }
}
```

> **Note:** `Event::fromArray()` and `Event::toArray()` handle conversion between the library's
> typed DTO and a plain PHP array matching the standard Nostr event JSON shape
> (`id`, `kind`, `pubkey`, `created_at`, `tags`, `content`, `sig`).

---

## 3. Wire the Services

Both `DriveService` and `FolderService` accept an `EventStoreInterface` through their
constructor. There is no service container required — plain instantiation is enough.

```php
<?php

use DecentNewsroom\NostrDrive\Service\DriveService;
use DecentNewsroom\NostrDrive\Service\FolderService;

$store = new MyNostrEventStore($relayClient);

$driveService  = new DriveService($store);
$folderService = new FolderService($store);
```

---

## 4. Full CRUD Example

```php
<?php

use DecentNewsroom\NostrDrive\Domain\Coordinate;
use DecentNewsroom\NostrDrive\Domain\FolderEntry;
use DecentNewsroom\NostrDrive\Domain\Meta;

// 64-character lowercase hex pubkey of the author
$pubkey = 'a1b2c3d4e5f6...'; // must be exactly 64 hex chars

// ---------- Drive ----------

$driveCoord  = new Coordinate(30042, $pubkey, 'my-drive');
$themesCoord = new Coordinate(30045, $pubkey, 'themes');
$magCoord    = new Coordinate(30045, $pubkey, 'magazines');

// Create a drive that mounts two root folders
$driveEvent = $driveService->create(
    $driveCoord,
    [$themesCoord, $magCoord],
    new Meta('My Drive', 'Personal workspace')
);

// Retrieve the drive
$drive = $driveService->get($driveCoord);
echo $drive->getTitle(); // "My Drive"

// Update root mounts (order = display order)
$driveService->setRoots($driveCoord, [$magCoord, $themesCoord]);

// ---------- Folder ----------

$folderEvent = $folderService->create(
    $themesCoord,
    [],
    new Meta('Themes', 'Website themes')
);

// Add an article entry
$articleCoord = new Coordinate(30024, $pubkey, 'my-article');
$entry = new FolderEntry(
    $articleCoord,
    'wss://relay.example',  // relay hint (optional)
    null,                   // last-seen event ID (optional)
    'My Article'            // name hint (optional)
);
$folderService->add($themesCoord, $entry);

// Remove an entry
$folderService->remove($themesCoord, $articleCoord);

// Move an entry between folders
$folderService->moveEntry($themesCoord, $magCoord, $articleCoord);

// Reorder entries (pass ALL existing coordinates in the desired order)
$coord1 = new Coordinate(30024, $pubkey, 'article-1');
$coord2 = new Coordinate(30024, $pubkey, 'article-2');
$folderService->reorder($themesCoord, [$coord2, $coord1]);

// Archive a folder (sets ["status","archived"] tag — does not delete from relay)
$folder = $folderService->get($themesCoord);
$folderService->archive($folder);
```

---

## 5. Allowed Folder Entry Kinds

`FolderService` enforces an allowlist via `KindValidator`. Only these kinds may be used
as folder entries:

| Kind    | Type                          |
|---------|-------------------------------|
| `30040` | Index                         |
| `30041` | AsciiDoc content              |
| `30024` | Markdown article              |
| `30023` | Markdown draft                |
| `30045` | Folder (nested)               |
| `31924` | Calendar (NIP-52)             |
| `31923` | Time-based calendar event     |
| `31922` | Date-based calendar event     |

Using any other kind throws `ValidationException`.

---

## 6. Common Pitfalls

| Problem | Cause | Fix |
|---------|-------|-----|
| `InvalidArgumentException: Pubkey must be a valid 64-character hex string` | Passing a bech32/npub key | Decode to raw hex first |
| `ValidationException: Drive coordinate must be kind 30042` | Wrong kind passed to `DriveService::create()` | Use `new Coordinate(30042, ...)` |
| `ValidationException: Folder coordinate must be kind 30045` | Wrong kind passed to `FolderService::create()` | Use `new Coordinate(30045, ...)` |
| `ValidationException: Kind X is not in the allowlist` | Entry kind not in the allowlist table above | Use only the allowed kinds |
| `ValidationException: Reorder must include all existing entries` | Passing a partial list to `reorder()` | Always pass every existing coordinate |
| `RuntimeException: Failed to publish ...` | `EventStoreInterface::publish()` returned `PublishResult::fail()` | Check your relay client / signing logic |

---

## See Also

- [Developer Docs](README.md) — domain model reference and tag shape
- [Full Specification](SPECIFICATION.md) — comprehensive technical spec
- [API Source](../src/) — annotated source for all services and contracts

