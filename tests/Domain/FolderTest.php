<?php

declare(strict_types=1);

namespace DecentNewsroom\NostrDrive\Tests\Domain;

use DecentNewsroom\NostrDrive\Domain\Coordinate;
use DecentNewsroom\NostrDrive\Domain\Folder;
use DecentNewsroom\NostrDrive\Domain\FolderEntry;
use PHPUnit\Framework\TestCase;

class FolderTest extends TestCase
{
    private const VALID_PUBKEY = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    public function testCanCreateFolder(): void
    {
        $coordinate = new Coordinate(30045, self::VALID_PUBKEY, 'my-folder');
        $title = 'My Folder';
        $description = 'A test folder';

        $folder = new Folder($coordinate, [], $title, $description);

        $this->assertSame($coordinate, $folder->getCoordinate());
        $this->assertSame($title, $folder->getTitle());
        $this->assertSame($description, $folder->getDescription());
        $this->assertSame(Folder::KIND, $folder->getKind());
        $this->assertSame(30045, $folder->getKind());
    }

    public function testCanCreateFolderWithEntries(): void
    {
        $coordinate = new Coordinate(30045, self::VALID_PUBKEY, 'my-folder');
        $entry1 = new FolderEntry(new Coordinate(30040, self::VALID_PUBKEY, 'file1'));
        $entry2 = new FolderEntry(new Coordinate(30041, self::VALID_PUBKEY, 'file2'));

        $folder = new Folder($coordinate, [$entry1, $entry2]);

        $entries = $folder->getEntries();
        $this->assertCount(2, $entries);
        $this->assertSame($entry1, $entries[0]);
        $this->assertSame($entry2, $entries[1]);
    }

    public function testCanAddEntry(): void
    {
        $coordinate = new Coordinate(30045, self::VALID_PUBKEY, 'my-folder');
        $folder = new Folder($coordinate);

        $entry = new FolderEntry(new Coordinate(30040, self::VALID_PUBKEY, 'file1'));
        $folder->addEntry($entry);

        $entries = $folder->getEntries();
        $this->assertCount(1, $entries);
        $this->assertSame($entry, $entries[0]);
    }

    public function testCanRemoveEntry(): void
    {
        $coordinate = new Coordinate(30045, self::VALID_PUBKEY, 'my-folder');
        $coord1 = new Coordinate(30040, self::VALID_PUBKEY, 'file1');
        $coord2 = new Coordinate(30041, self::VALID_PUBKEY, 'file2');

        $entry1 = new FolderEntry($coord1);
        $entry2 = new FolderEntry($coord2);

        $folder = new Folder($coordinate, [$entry1, $entry2]);
        $folder->removeEntry($coord1);

        $entries = $folder->getEntries();
        $this->assertCount(1, $entries);
        $this->assertTrue($entries[0]->getCoordinate()->equals($coord2));
    }

    public function testCanSetEntries(): void
    {
        $coordinate = new Coordinate(30045, self::VALID_PUBKEY, 'my-folder');
        $folder = new Folder($coordinate);

        $entries = [
            new FolderEntry(new Coordinate(30040, self::VALID_PUBKEY, 'file1')),
            new FolderEntry(new Coordinate(30041, self::VALID_PUBKEY, 'file2')),
        ];

        $folder->setEntries($entries);

        $this->assertSame($entries, $folder->getEntries());
    }

    public function testHasEntry(): void
    {
        $coordinate = new Coordinate(30045, self::VALID_PUBKEY, 'my-folder');
        $coord1 = new Coordinate(30040, self::VALID_PUBKEY, 'file1');
        $coord2 = new Coordinate(30041, self::VALID_PUBKEY, 'file2');

        $entry1 = new FolderEntry($coord1);
        $folder = new Folder($coordinate, [$entry1]);

        $this->assertTrue($folder->hasEntry($coord1));
        $this->assertFalse($folder->hasEntry($coord2));
    }

    public function testThrowsOnInvalidKind(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Folder coordinate must be kind 30045');

        $invalidCoordinate = new Coordinate(30042, self::VALID_PUBKEY, 'wrong-kind');
        new Folder($invalidCoordinate);
    }

    public function testThrowsOnInvalidEntryType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('All entries must be FolderEntry instances');

        $coordinate = new Coordinate(30045, self::VALID_PUBKEY, 'my-folder');
        new Folder($coordinate, ['not an entry']);
    }
}
