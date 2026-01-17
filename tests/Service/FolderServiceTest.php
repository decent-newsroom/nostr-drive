<?php

declare(strict_types=1);

namespace DecentNewsroom\NostrDrive\Tests\Service;

use DecentNewsroom\NostrDrive\Contract\EventStoreInterface;
use DecentNewsroom\NostrDrive\Domain\Coordinate;
use DecentNewsroom\NostrDrive\Domain\FolderEntry;
use DecentNewsroom\NostrDrive\Exception\InvalidKindException;
use DecentNewsroom\NostrDrive\Exception\NotFoundException;
use DecentNewsroom\NostrDrive\Exception\ValidationException;
use DecentNewsroom\NostrDrive\Service\FolderService;
use PHPUnit\Framework\TestCase;

class FolderServiceTest extends TestCase
{
    private const VALID_PUBKEY = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    private EventStoreInterface $eventStore;
    private FolderService $service;

    protected function setUp(): void
    {
        $this->eventStore = $this->createMock(EventStoreInterface::class);
        $this->service = new FolderService($this->eventStore);
    }

    public function testCanCreateFolder(): void
    {
        $this->eventStore
            ->expects($this->once())
            ->method('publish')
            ->willReturn(true);

        $coordinate = new Coordinate(30045, self::VALID_PUBKEY, 'my-folder');
        $folder = $this->service->create($coordinate, [], 'My Folder', 'Description');

        $this->assertTrue($folder->getCoordinate()->equals($coordinate));
        $this->assertSame('My Folder', $folder->getTitle());
        $this->assertSame('Description', $folder->getDescription());
    }

    public function testCanCreateFolderWithEntries(): void
    {
        $this->eventStore
            ->expects($this->once())
            ->method('publish')
            ->willReturn(true);

        $coordinate = new Coordinate(30045, self::VALID_PUBKEY, 'my-folder');
        $entry1 = new FolderEntry(new Coordinate(30040, self::VALID_PUBKEY, 'file1'));
        $entry2 = new FolderEntry(new Coordinate(30041, self::VALID_PUBKEY, 'file2'));

        $folder = $this->service->create($coordinate, [$entry1, $entry2], 'My Folder');

        $this->assertCount(2, $folder->getEntries());
    }

    public function testCreateThrowsExceptionForInvalidKind(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Folder coordinate must be kind 30045');

        $invalidCoordinate = new Coordinate(30042, self::VALID_PUBKEY, 'wrong');
        $this->service->create($invalidCoordinate);
    }

    public function testCreateThrowsExceptionForInvalidEntryKind(): void
    {
        $this->expectException(InvalidKindException::class);

        $coordinate = new Coordinate(30045, self::VALID_PUBKEY, 'my-folder');
        $invalidEntry = new FolderEntry(new Coordinate(30042, self::VALID_PUBKEY, 'drive'));

        $this->service->create($coordinate, [$invalidEntry]);
    }

    public function testCanGetFolder(): void
    {
        $event = [
            'id' => 'event123',
            'kind' => 30045,
            'pubkey' => self::VALID_PUBKEY,
            'created_at' => 1234567890,
            'content' => '',
            'tags' => [
                ['d', 'my-folder'],
                ['title', 'My Folder'],
                ['description', 'Test folder'],
                ['a', '30040:' . self::VALID_PUBKEY . ':file1', 'wss://relay.example'],
                ['a', '30041:' . self::VALID_PUBKEY . ':file2', 'wss://relay.example', 'event_hint', 'File 2'],
            ],
        ];

        $this->eventStore
            ->expects($this->once())
            ->method('getLatestByCoordinate')
            ->willReturn($event);

        $coordinate = new Coordinate(30045, self::VALID_PUBKEY, 'my-folder');
        $folder = $this->service->get($coordinate);

        $this->assertSame('My Folder', $folder->getTitle());
        $this->assertSame('Test folder', $folder->getDescription());
        $this->assertCount(2, $folder->getEntries());

        $entries = $folder->getEntries();
        $this->assertSame('wss://relay.example', $entries[0]->getRelayHint());
        $this->assertSame('File 2', $entries[1]->getNameHint());
    }

    public function testGetThrowsNotFoundExceptionWhenFolderDoesNotExist(): void
    {
        $this->expectException(NotFoundException::class);

        $this->eventStore
            ->expects($this->once())
            ->method('getLatestByCoordinate')
            ->willReturn(null);

        $coordinate = new Coordinate(30045, self::VALID_PUBKEY, 'non-existent');
        $this->service->get($coordinate);
    }

    public function testCanAddEntryToFolder(): void
    {
        $event = [
            'id' => 'event123',
            'kind' => 30045,
            'pubkey' => self::VALID_PUBKEY,
            'created_at' => 1234567890,
            'content' => '',
            'tags' => [
                ['d', 'my-folder'],
                ['title', 'My Folder'],
            ],
        ];

        $this->eventStore
            ->expects($this->once())
            ->method('getLatestByCoordinate')
            ->willReturn($event);

        $this->eventStore
            ->expects($this->once())
            ->method('publish')
            ->willReturn(true);

        $folderCoord = new Coordinate(30045, self::VALID_PUBKEY, 'my-folder');
        $entryCoord = new Coordinate(30040, self::VALID_PUBKEY, 'file1');
        $entry = new FolderEntry($entryCoord, 'wss://relay.example', null, 'File 1');

        $folder = $this->service->addEntry($folderCoord, $entry);

        $entries = $folder->getEntries();
        $this->assertCount(1, $entries);
        $this->assertTrue($entries[0]->getCoordinate()->equals($entryCoord));
    }

    public function testAddEntryThrowsExceptionForDuplicate(): void
    {
        $entryCoord = new Coordinate(30040, self::VALID_PUBKEY, 'file1');

        $event = [
            'id' => 'event123',
            'kind' => 30045,
            'pubkey' => self::VALID_PUBKEY,
            'created_at' => 1234567890,
            'content' => '',
            'tags' => [
                ['d', 'my-folder'],
                ['title', 'My Folder'],
                ['a', $entryCoord->toString()],
            ],
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('already exists');

        $this->eventStore
            ->expects($this->once())
            ->method('getLatestByCoordinate')
            ->willReturn($event);

        $folderCoord = new Coordinate(30045, self::VALID_PUBKEY, 'my-folder');
        $entry = new FolderEntry($entryCoord);

        $this->service->addEntry($folderCoord, $entry);
    }

    public function testCanRemoveEntryFromFolder(): void
    {
        $entryCoord = new Coordinate(30040, self::VALID_PUBKEY, 'file1');

        $event = [
            'id' => 'event123',
            'kind' => 30045,
            'pubkey' => self::VALID_PUBKEY,
            'created_at' => 1234567890,
            'content' => '',
            'tags' => [
                ['d', 'my-folder'],
                ['title', 'My Folder'],
                ['a', $entryCoord->toString()],
            ],
        ];

        $this->eventStore
            ->expects($this->once())
            ->method('getLatestByCoordinate')
            ->willReturn($event);

        $this->eventStore
            ->expects($this->once())
            ->method('publish')
            ->willReturn(true);

        $folderCoord = new Coordinate(30045, self::VALID_PUBKEY, 'my-folder');
        $folder = $this->service->removeEntry($folderCoord, $entryCoord);

        $this->assertCount(0, $folder->getEntries());
    }

    public function testCanMoveEntry(): void
    {
        $entryCoord = new Coordinate(30040, self::VALID_PUBKEY, 'file1');

        $srcEvent = [
            'id' => 'event123',
            'kind' => 30045,
            'pubkey' => self::VALID_PUBKEY,
            'created_at' => 1234567890,
            'content' => '',
            'tags' => [
                ['d', 'src-folder'],
                ['title', 'Source'],
                ['a', $entryCoord->toString()],
            ],
        ];

        $dstEvent = [
            'id' => 'event456',
            'kind' => 30045,
            'pubkey' => self::VALID_PUBKEY,
            'created_at' => 1234567890,
            'content' => '',
            'tags' => [
                ['d', 'dst-folder'],
                ['title', 'Destination'],
            ],
        ];

        $this->eventStore
            ->expects($this->exactly(2))
            ->method('getLatestByCoordinate')
            ->willReturnOnConsecutiveCalls($srcEvent, $dstEvent);

        $this->eventStore
            ->expects($this->exactly(2))
            ->method('publish')
            ->willReturn(true);

        $srcCoord = new Coordinate(30045, self::VALID_PUBKEY, 'src-folder');
        $dstCoord = new Coordinate(30045, self::VALID_PUBKEY, 'dst-folder');

        $result = $this->service->moveEntry($srcCoord, $dstCoord, $entryCoord);

        $this->assertCount(0, $result['src']->getEntries());
        $this->assertCount(1, $result['dst']->getEntries());
    }

    public function testCanReorderEntries(): void
    {
        $coord1 = new Coordinate(30040, self::VALID_PUBKEY, 'file1');
        $coord2 = new Coordinate(30041, self::VALID_PUBKEY, 'file2');

        $event = [
            'id' => 'event123',
            'kind' => 30045,
            'pubkey' => self::VALID_PUBKEY,
            'created_at' => 1234567890,
            'content' => '',
            'tags' => [
                ['d', 'my-folder'],
                ['title', 'My Folder'],
                ['a', $coord1->toString()],
                ['a', $coord2->toString()],
            ],
        ];

        $this->eventStore
            ->expects($this->once())
            ->method('getLatestByCoordinate')
            ->willReturn($event);

        $this->eventStore
            ->expects($this->once())
            ->method('publish')
            ->willReturn(true);

        $folderCoord = new Coordinate(30045, self::VALID_PUBKEY, 'my-folder');
        $folder = $this->service->reorderEntries($folderCoord, [$coord2, $coord1]);

        $entries = $folder->getEntries();
        $this->assertTrue($entries[0]->getCoordinate()->equals($coord2));
        $this->assertTrue($entries[1]->getCoordinate()->equals($coord1));
    }

    public function testReorderEntriesThrowsExceptionForMissingCoordinate(): void
    {
        $coord1 = new Coordinate(30040, self::VALID_PUBKEY, 'file1');
        $coord2 = new Coordinate(30041, self::VALID_PUBKEY, 'file2');
        $missingCoord = new Coordinate(30040, self::VALID_PUBKEY, 'missing');

        $event = [
            'id' => 'event123',
            'kind' => 30045,
            'pubkey' => self::VALID_PUBKEY,
            'created_at' => 1234567890,
            'content' => '',
            'tags' => [
                ['d', 'my-folder'],
                ['title', 'My Folder'],
                ['a', $coord1->toString()],
                ['a', $coord2->toString()],
            ],
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('not found in folder');

        $this->eventStore
            ->expects($this->once())
            ->method('getLatestByCoordinate')
            ->willReturn($event);

        $folderCoord = new Coordinate(30045, self::VALID_PUBKEY, 'my-folder');
        $this->service->reorderEntries($folderCoord, [$missingCoord, $coord1]);
    }

    public function testCanSetEntries(): void
    {
        $event = [
            'id' => 'event123',
            'kind' => 30045,
            'pubkey' => self::VALID_PUBKEY,
            'created_at' => 1234567890,
            'content' => '',
            'tags' => [
                ['d', 'my-folder'],
                ['title', 'My Folder'],
            ],
        ];

        $this->eventStore
            ->expects($this->once())
            ->method('getLatestByCoordinate')
            ->willReturn($event);

        $this->eventStore
            ->expects($this->once())
            ->method('publish')
            ->willReturn(true);

        $folderCoord = new Coordinate(30045, self::VALID_PUBKEY, 'my-folder');
        $entry1 = new FolderEntry(new Coordinate(30040, self::VALID_PUBKEY, 'file1'));
        $entry2 = new FolderEntry(new Coordinate(30041, self::VALID_PUBKEY, 'file2'));

        $folder = $this->service->setEntries($folderCoord, [$entry1, $entry2]);

        $this->assertCount(2, $folder->getEntries());
    }

    public function testCanArchiveFolder(): void
    {
        $this->eventStore
            ->expects($this->once())
            ->method('publish')
            ->with($this->callback(function ($event) {
                $hasArchiveTag = false;
                foreach ($event['tags'] as $tag) {
                    if ($tag[0] === 'status' && $tag[1] === 'archived') {
                        $hasArchiveTag = true;
                        break;
                    }
                }
                return $hasArchiveTag;
            }))
            ->willReturn(true);

        $coordinate = new Coordinate(30045, self::VALID_PUBKEY, 'my-folder');
        $folder = new \DecentNewsroom\NostrDrive\Domain\Folder($coordinate);

        $result = $this->service->archive($folder);

        $this->assertTrue($result);
    }
}
