<?php

declare(strict_types=1);

namespace DecentNewsroom\NostrDrive\Tests\Domain;

use DecentNewsroom\NostrDrive\Domain\FolderEntry;
use PHPUnit\Framework\TestCase;

class FolderEntryTest extends TestCase
{
    public function testCanCreateFolderEntry(): void
    {
        $eventId = 'event123';
        $kind = 30040;
        $position = 0;

        $entry = new FolderEntry($eventId, $kind, $position);

        $this->assertSame($eventId, $entry->getEventId());
        $this->assertSame($kind, $entry->getKind());
        $this->assertSame($position, $entry->getPosition());
        $this->assertNull($entry->getPubkey());
        $this->assertNull($entry->getIdentifier());
        $this->assertFalse($entry->isAddressable());
    }

    public function testCanCreateAddressableEntry(): void
    {
        $eventId = 'event123';
        $kind = 30040;
        $position = 0;
        $pubkey = 'pubkey123';
        $identifier = 'my-file';

        $entry = new FolderEntry($eventId, $kind, $position, $pubkey, $identifier);

        $this->assertSame($eventId, $entry->getEventId());
        $this->assertSame($kind, $entry->getKind());
        $this->assertSame($position, $entry->getPosition());
        $this->assertSame($pubkey, $entry->getPubkey());
        $this->assertSame($identifier, $entry->getIdentifier());
        $this->assertTrue($entry->isAddressable());
        $this->assertSame("{$kind}:{$pubkey}:{$identifier}", $entry->toCoordinate());
    }

    public function testCanSetPosition(): void
    {
        $entry = new FolderEntry('event123', 30040, 0);

        $entry->setPosition(5);

        $this->assertSame(5, $entry->getPosition());
    }
}
