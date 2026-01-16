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
        $metadata = ['key' => 'value'];

        $entry = new FolderEntry($eventId, $kind, $position, $metadata);

        $this->assertSame($eventId, $entry->getEventId());
        $this->assertSame($kind, $entry->getKind());
        $this->assertSame($position, $entry->getPosition());
        $this->assertSame($metadata, $entry->getMetadata());
    }

    public function testCanSetPosition(): void
    {
        $entry = new FolderEntry('event123', 30040, 0);

        $entry->setPosition(5);

        $this->assertSame(5, $entry->getPosition());
    }

    public function testCanSetMetadata(): void
    {
        $entry = new FolderEntry('event123', 30040, 0);

        $metadata = ['new' => 'data'];
        $entry->setMetadata($metadata);

        $this->assertSame($metadata, $entry->getMetadata());
    }
}
