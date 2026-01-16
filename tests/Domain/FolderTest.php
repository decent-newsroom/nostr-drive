<?php

declare(strict_types=1);

namespace DecentNewsroom\NostrDrive\Tests\Domain;

use DecentNewsroom\NostrDrive\Domain\Address;
use DecentNewsroom\NostrDrive\Domain\Folder;
use DecentNewsroom\NostrDrive\Domain\FolderEntry;
use PHPUnit\Framework\TestCase;

class FolderTest extends TestCase
{
    public function testCanCreateFolder(): void
    {
        $address = new Address('pubkey123');
        $identifier = 'my-folder';
        $name = 'My Folder';

        $folder = new Folder($address, $identifier, $name);

        $this->assertSame($address, $folder->getAddress());
        $this->assertSame($identifier, $folder->getIdentifier());
        $this->assertSame($name, $folder->getName());
        $this->assertSame(Folder::KIND, $folder->getKind());
        $this->assertSame(30045, $folder->getKind());
    }

    public function testCanAddEntry(): void
    {
        $address = new Address('pubkey123');
        $folder = new Folder($address, 'id', 'Name');

        $entry = new FolderEntry('event123', 30040, 0);
        $folder->addEntry($entry);

        $entries = $folder->getEntries();
        $this->assertCount(1, $entries);
        $this->assertSame($entry, $entries[0]);
    }

    public function testCanRemoveEntry(): void
    {
        $address = new Address('pubkey123');
        $folder = new Folder($address, 'id', 'Name');

        $entry1 = new FolderEntry('event1', 30040, 0);
        $entry2 = new FolderEntry('event2', 30041, 1);

        $folder->addEntry($entry1);
        $folder->addEntry($entry2);

        $folder->removeEntry('event1');

        $entries = $folder->getEntries();
        $this->assertCount(1, $entries);
        $this->assertSame('event2', $entries[0]->getEventId());
    }

    public function testCanSetEntries(): void
    {
        $address = new Address('pubkey123');
        $folder = new Folder($address, 'id', 'Name');

        $entries = [
            new FolderEntry('event1', 30040, 0),
            new FolderEntry('event2', 30041, 1),
        ];

        $folder->setEntries($entries);

        $this->assertSame($entries, $folder->getEntries());
    }
}
