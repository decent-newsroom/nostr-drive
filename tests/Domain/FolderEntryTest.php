<?php

declare(strict_types=1);

namespace DecentNewsroom\NostrDrive\Tests\Domain;

use DecentNewsroom\NostrDrive\Domain\Coordinate;
use DecentNewsroom\NostrDrive\Domain\FolderEntry;
use PHPUnit\Framework\TestCase;

class FolderEntryTest extends TestCase
{
    private const VALID_PUBKEY = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    public function testCanCreateFolderEntry(): void
    {
        $coordinate = new Coordinate(30040, self::VALID_PUBKEY, 'my-file');

        $entry = new FolderEntry($coordinate);

        $this->assertSame($coordinate, $entry->getCoordinate());
        $this->assertNull($entry->getRelayHint());
        $this->assertNull($entry->getLastSeenEventId());
        $this->assertNull($entry->getNameHint());
    }

    public function testCanCreateEntryWithHints(): void
    {
        $coordinate = new Coordinate(30040, self::VALID_PUBKEY, 'my-file');
        $relayHint = 'wss://relay.example';
        $eventId = 'event123';
        $nameHint = 'My File';

        $entry = new FolderEntry($coordinate, $relayHint, $eventId, $nameHint);

        $this->assertSame($coordinate, $entry->getCoordinate());
        $this->assertSame($relayHint, $entry->getRelayHint());
        $this->assertSame($eventId, $entry->getLastSeenEventId());
        $this->assertSame($nameHint, $entry->getNameHint());
    }

    public function testCanUpdateHints(): void
    {
        $coordinate = new Coordinate(30040, self::VALID_PUBKEY, 'my-file');
        $entry = new FolderEntry($coordinate);

        $newEntry = $entry->withHints('wss://relay.example', 'event123', 'My File');

        // Original unchanged (readonly)
        $this->assertNull($entry->getRelayHint());

        // New entry has hints
        $this->assertSame('wss://relay.example', $newEntry->getRelayHint());
        $this->assertSame('event123', $newEntry->getLastSeenEventId());
        $this->assertSame('My File', $newEntry->getNameHint());
    }

    public function testWithHintsPreservesExisting(): void
    {
        $coordinate = new Coordinate(30040, self::VALID_PUBKEY, 'my-file');
        $entry = new FolderEntry($coordinate, 'wss://old.relay', 'oldEvent', 'Old Name');

        $newEntry = $entry->withHints(null, 'newEvent');

        $this->assertSame('wss://old.relay', $newEntry->getRelayHint());
        $this->assertSame('newEvent', $newEntry->getLastSeenEventId());
        $this->assertSame('Old Name', $newEntry->getNameHint());
    }
}
