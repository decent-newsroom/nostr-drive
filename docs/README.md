# Nostr Drive PHP Library

A framework-agnostic PHP library that implements a filesystem-like hierarchy on Nostr using Drive events (kind:30042) and Folder events (kind:30045). It provides deterministic CRUD for drives and folders, plus membership operations (add/remove/move/reorder) over addressable event coordinates.

## Requirements

- PHP 8.2 or higher
- Composer

## Installation

```bash
composer require decent-newsroom/nostr-drive
```

## Architecture

The library is organized into several layers:

### Domain
Contains the core domain models:
- **Address**: Represents a Nostr address - can be either a pubkey or a coordinate (kind:pubkey:d-tag for addressable events)
- **Drive**: Represents a Drive event (kind:30042), the root container for organizing files and folders
- **Folder**: Represents a Folder event (kind:30045), which can contain entries of allowed kinds
- **FolderEntry**: Represents an entry within a folder, supporting both event IDs and addressable coordinates

### Contract
Defines the interfaces that must be implemented:
- **EventStoreInterface**: Interface for interacting with the Nostr event store
  - `getLatestByAddress()`: Get the latest event for a given address and kind
  - `getById()`: Get an event by its ID
  - `getLatestByAddresses()`: Get the latest events for multiple addresses
  - `publish()`: Publish an event to the Nostr network

### Service
Provides business logic for managing drives and folders:
- **DriveService**: CRUD operations for Drive entities
- **FolderService**: CRUD operations for Folder entities plus entry management

### Validation
- **KindValidator**: Validates event kinds for folder entries. Only allows:
  - 30040, 30041: File events
  - 30024, 30023: Long-form content
  - 31924, 31923, 31922: App-specific events

### Exception
Custom exceptions for error handling:
- **NostrDriveException**: Base exception
- **ValidationException**: Thrown when validation fails
- **NotFoundException**: Thrown when an entity is not found
- **InvalidKindException**: Thrown when an invalid kind is used

## Usage

### Implementing the EventStore

First, you need to implement the `EventStoreInterface` to connect to your Nostr relays:

```php
use DecentNewsroom\NostrDrive\Contract\EventStoreInterface;
use DecentNewsroom\NostrDrive\Domain\Address;

class MyEventStore implements EventStoreInterface
{
    public function getLatestByAddress(Address $address, int $kind, ?string $identifier = null): ?array
    {
        // Implement your logic to fetch the latest event from Nostr relays
        // Return null if not found
    }

    public function getById(string $eventId): ?array
    {
        // Implement your logic to fetch an event by ID
        // Return null if not found
    }

    public function getLatestByAddresses(array $addresses, int $kind): array
    {
        // Implement your logic to fetch latest events for multiple addresses
        // Return array indexed by address pubkey
    }

    public function publish(array $event): bool
    {
        // Implement your logic to publish an event to Nostr relays
        // Return true if successful
    }
}
```

### Working with Drives

```php
use DecentNewsroom\NostrDrive\Service\DriveService;
use DecentNewsroom\NostrDrive\Domain\Address;

$eventStore = new MyEventStore();
$driveService = new DriveService($eventStore);

// Create a new drive
$address = new Address('pubkey123');
$drive = $driveService->create(
    $address,
    'my-drive',      // identifier (d-tag)
    'My Drive',      // name
    []               // additional tags
);

// Create an address as a coordinate
$coordinateAddress = new Address('pubkey123', [], 30042, 'my-drive');
echo $coordinateAddress->toString(); // "30042:pubkey123:my-drive"

// Get a drive
$drive = $driveService->get($address, 'my-drive');

// Update a drive
$drive->setName('Updated Drive Name');
$driveService->update($drive);

// Delete a drive
$driveService->delete($drive);
```

### Working with Folders

```php
use DecentNewsroom\NostrDrive\Service\FolderService;
use DecentNewsroom\NostrDrive\Domain\Address;

$eventStore = new MyEventStore();
$folderService = new FolderService($eventStore);

// Create a new folder
$address = new Address('pubkey123');
$folder = $folderService->create(
    $address,
    'my-folder',     // identifier (d-tag)
    'My Folder',     // name
    []               // additional tags
);

// Get a folder
$folder = $folderService->get($address, 'my-folder');

// Update a folder
$folder->setName('Updated Folder Name');
$folderService->update($folder);
```

### Managing Folder Entries

```php
// Add an entry to a folder (only allowed kinds: 30040, 30041, 30024, 30023, 31924, 31923, 31922)
// Regular event (by event ID only)
$folder = $folderService->addEntry(
    $folder,
    'event123',      // event ID
    30040            // event kind
);

// Addressable event (with coordinate - both 'e' and 'a' tags will be used)
$folder = $folderService->addEntry(
    $folder,
    'event456',      // event ID
    30041,           // event kind
    'pubkey789',     // pubkey for addressable event
    'my-file'        // d-tag identifier for addressable event
);

// Remove an entry from a folder
$folder = $folderService->removeEntry($folder, 'event123');

// Reorder entries
$folder = $folderService->reorderEntries(
    $folder,
    ['event2', 'event1', 'event3']  // new order of event IDs
);

// Access folder entries
$entries = $folder->getEntries();
foreach ($entries as $entry) {
    echo "Event ID: " . $entry->getEventId() . "\n";
    echo "Kind: " . $entry->getKind() . "\n";
    echo "Position: " . $entry->getPosition() . "\n";
}
```

### Validation

The library automatically validates event kinds when adding entries to folders:

```php
try {
    // This will throw an InvalidKindException because kind 1 is not allowed
    $folderService->addEntry($folder, 'event123', 1);
} catch (\DecentNewsroom\NostrDrive\Exception\InvalidKindException $e) {
    echo "Invalid kind: " . $e->getMessage();
}

// Check if a kind is allowed
use DecentNewsroom\NostrDrive\Validation\KindValidator;

if (KindValidator::isAllowed(30040)) {
    echo "Kind 30040 is allowed";
}

// Get all allowed kinds
$allowedKinds = KindValidator::getAllowedKinds();
```

## Event Structure

### Drive Event (kind:30042)

Drive events have **empty content**. All information is stored in tags.

```json
{
  "kind": 30042,
  "pubkey": "author_pubkey",
  "created_at": 1234567890,
  "content": "",
  "tags": [
    ["d", "drive-identifier"],
    ["name", "My Drive"]
  ]
}
```

### Folder Event (kind:30045)

Folder events have **empty content**. All information is stored in tags.
Entries use both 'e' tags (event ID) and 'a' tags (addressable coordinate) for addressable events.

```json
{
  "kind": 30045,
  "pubkey": "author_pubkey",
  "created_at": 1234567890,
  "content": "",
  "tags": [
    ["d", "folder-identifier"],
    ["name", "My Folder"],
    ["e", "event_id_1", "", "30040", "0"],
    ["e", "event_id_2", "", "30041", "1"],
    ["a", "30041:pubkey789:my-file", "", "1"]
  ]
}
```

Note: The 'a' tag is only included for addressable replaceable events. Regular events only have an 'e' tag.

## Testing

Run the test suite with PHPUnit:

```bash
composer install
vendor/bin/phpunit
```

## License

This library is licensed under the MIT License. See the [LICENSE](../LICENSE) file for details.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.
