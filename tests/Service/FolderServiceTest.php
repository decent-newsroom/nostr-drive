<?php

declare(strict_types=1);

namespace DecentNewsroom\NostrDrive\Tests\Service;

use DecentNewsroom\NostrDrive\Contract\EventStoreInterface;
use DecentNewsroom\NostrDrive\Domain\Coordinate;
use DecentNewsroom\NostrDrive\Domain\Event;
use DecentNewsroom\NostrDrive\Domain\Folder;
use DecentNewsroom\NostrDrive\Domain\FolderEntry;
use DecentNewsroom\NostrDrive\Domain\Meta;
use DecentNewsroom\NostrDrive\Domain\PublishResult;
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

    // -------------------------------------------------------------------------
    // create()
    // -------------------------------------------------------------------------

    public function testCanCreateFolder(): void
    {
        $this->eventStore
            ->expects($this->once())
            ->method('publish')
            ->willReturn(PublishResult::ok());

        $coordinate = new Coordinate(30045, self::VALID_PUBKEY, 'my-folder');
        $event = $this->service->create($coordinate, [], new Meta('My Folder', 'Description'));

        $this->assertSame(30045, $event->kind);
        $this->assertSame(self::VALID_PUBKEY, $event->pubkey);
        $this->assertSame('my-folder', $event->getTagValue('d'));
        $this->assertSame('My Folder', $event->getTagValue('title'));
        $this->assertSame('Description', $event->getTagValue('description'));
    }

    public function testCanCreateFolderWithEntries(): void
    {
        $this->eventStore
            ->expects($this->once())
            ->method('publish')
            ->willReturn(PublishResult::ok());

        $coordinate = new Coordinate(30045, self::VALID_PUBKEY, 'my-folder');
        $entry1 = new FolderEntry(new Coordinate(30040, self::VALID_PUBKEY, 'file1'));
        $entry2 = new FolderEntry(new Coordinate(30041, self::VALID_PUBKEY, 'file2'));

        $event = $this->service->create($coordinate, [$entry1, $entry2], new Meta('My Folder'));

        // Assert `a` tags for both entries
        $aTags = $event->getTagValues('a');
        $this->assertCount(2, $aTags);
        $this->assertSame($entry1->getCoordinate()->toString(), $aTags[0][1]);
        $this->assertSame($entry2->getCoordinate()->toString(), $aTags[1][1]);
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

    // -------------------------------------------------------------------------
    // get()
    // -------------------------------------------------------------------------

    public function testCanGetFolder(): void
    {
        $rawEvent = [
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
            ->willReturn(Event::fromArray($rawEvent));

        $coordinate = new Coordinate(30045, self::VALID_PUBKEY, 'my-folder');
        $folder = $this->service->get($coordinate);

        $this->assertSame('My Folder', $folder->getTitle());
        $this->assertSame('Test folder', $folder->getDescription());
        $this->assertCount(2, $folder->getEntries());

        $entries = $folder->getEntries();
        $this->assertSame('wss://relay.example', $entries[0]->getRelayHint());
        $this->assertSame('File 2', $entries[1]->getNameHint());
        $this->assertSame('event_hint', $entries[1]->getLastSeenEventId());
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

    // -------------------------------------------------------------------------
    // add()
    // -------------------------------------------------------------------------

    public function testCanAddEntryToFolder(): void
    {
        $rawEvent = [
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
            ->willReturn(Event::fromArray($rawEvent));

        $this->eventStore
            ->expects($this->once())
            ->method('publish')
            ->willReturn(PublishResult::ok());

        $folderCoord = new Coordinate(30045, self::VALID_PUBKEY, 'my-folder');
        $entryCoord  = new Coordinate(30040, self::VALID_PUBKEY, 'file1');
        $entry       = new FolderEntry($entryCoord, 'wss://relay.example', null, 'File 1');

        $event = $this->service->add($folderCoord, $entry);

        // Assert the published event has the correct `a` tag
        $aTags = $event->getTagValues('a');
        $this->assertCount(1, $aTags);
        $this->assertSame($entryCoord->toString(), $aTags[0][1]);
        $this->assertSame('wss://relay.example', $aTags[0][2]);
        $this->assertSame('', $aTags[0][3]);   // empty last-event-id position
        $this->assertSame('File 1', $aTags[0][4]);
    }

    public function testAddThrowsExceptionForDuplicate(): void
    {
        $entryCoord = new Coordinate(30040, self::VALID_PUBKEY, 'file1');

        $rawEvent = [
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
            ->willReturn(Event::fromArray($rawEvent));

        $folderCoord = new Coordinate(30045, self::VALID_PUBKEY, 'my-folder');
        $this->service->add($folderCoord, new FolderEntry($entryCoord));
    }

    // -------------------------------------------------------------------------
    // remove()
    // -------------------------------------------------------------------------

    public function testCanRemoveEntryFromFolder(): void
    {
        $entryCoord = new Coordinate(30040, self::VALID_PUBKEY, 'file1');

        $rawEvent = [
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
            ->willReturn(Event::fromArray($rawEvent));

        $this->eventStore
            ->expects($this->once())
            ->method('publish')
            ->willReturn(PublishResult::ok());

        $folderCoord = new Coordinate(30045, self::VALID_PUBKEY, 'my-folder');
        $event = $this->service->remove($folderCoord, $entryCoord);

        // The published event must have no `a` tags
        $this->assertCount(0, $event->getTagValues('a'));
    }

    public function testRemoveProducesCorrectTagArray(): void
    {
        $coord1 = new Coordinate(30040, self::VALID_PUBKEY, 'file1');
        $coord2 = new Coordinate(30041, self::VALID_PUBKEY, 'file2');

        $rawEvent = [
            'id' => 'event123',
            'kind' => 30045,
            'pubkey' => self::VALID_PUBKEY,
            'created_at' => 1234567890,
            'content' => '',
            'tags' => [
                ['d', 'my-folder'],
                ['a', $coord1->toString()],
                ['a', $coord2->toString()],
            ],
        ];

        $this->eventStore->method('getLatestByCoordinate')->willReturn(Event::fromArray($rawEvent));
        $this->eventStore->method('publish')->willReturn(PublishResult::ok());

        $event = $this->service->remove(
            new Coordinate(30045, self::VALID_PUBKEY, 'my-folder'),
            $coord1
        );

        $aTags = $event->getTagValues('a');
        $this->assertCount(1, $aTags);
        $this->assertSame($coord2->toString(), $aTags[0][1]);
    }

    // -------------------------------------------------------------------------
    // reorder()
    // -------------------------------------------------------------------------

    public function testCanReorderEntries(): void
    {
        $coord1 = new Coordinate(30040, self::VALID_PUBKEY, 'file1');
        $coord2 = new Coordinate(30041, self::VALID_PUBKEY, 'file2');

        $rawEvent = [
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
            ->willReturn(Event::fromArray($rawEvent));

        $this->eventStore
            ->expects($this->once())
            ->method('publish')
            ->willReturn(PublishResult::ok());

        $folderCoord = new Coordinate(30045, self::VALID_PUBKEY, 'my-folder');
        $event = $this->service->reorder($folderCoord, [$coord2, $coord1]);

        // Assert `a` tags are in the new order
        $aTags = $event->getTagValues('a');
        $this->assertCount(2, $aTags);
        $this->assertSame($coord2->toString(), $aTags[0][1]);
        $this->assertSame($coord1->toString(), $aTags[1][1]);
    }

    public function testReorderThrowsExceptionForMissingCoordinate(): void
    {
        $coord1 = new Coordinate(30040, self::VALID_PUBKEY, 'file1');
        $coord2 = new Coordinate(30041, self::VALID_PUBKEY, 'file2');
        $missingCoord = new Coordinate(30040, self::VALID_PUBKEY, 'missing');

        $rawEvent = [
            'id' => 'event123',
            'kind' => 30045,
            'pubkey' => self::VALID_PUBKEY,
            'created_at' => 1234567890,
            'content' => '',
            'tags' => [
                ['d', 'my-folder'],
                ['a', $coord1->toString()],
                ['a', $coord2->toString()],
            ],
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('not found in folder');

        $this->eventStore
            ->expects($this->once())
            ->method('getLatestByCoordinate')
            ->willReturn(Event::fromArray($rawEvent));

        $folderCoord = new Coordinate(30045, self::VALID_PUBKEY, 'my-folder');
        $this->service->reorder($folderCoord, [$missingCoord, $coord1]);
    }

    // -------------------------------------------------------------------------
    // moveEntry()
    // -------------------------------------------------------------------------

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
            ],
        ];

        $this->eventStore
            ->expects($this->exactly(2))
            ->method('getLatestByCoordinate')
            ->willReturnOnConsecutiveCalls(
                Event::fromArray($srcEvent),
                Event::fromArray($dstEvent)
            );

        $this->eventStore
            ->expects($this->exactly(2))
            ->method('publish')
            ->willReturn(PublishResult::ok());

        $srcCoord = new Coordinate(30045, self::VALID_PUBKEY, 'src-folder');
        $dstCoord = new Coordinate(30045, self::VALID_PUBKEY, 'dst-folder');

        $result = $this->service->moveEntry($srcCoord, $dstCoord, $entryCoord);

        // Source should have no `a` tags, destination should have 1
        $this->assertCount(0, $result['src']->getTagValues('a'));
        $this->assertCount(1, $result['dst']->getTagValues('a'));
        $this->assertSame($entryCoord->toString(), $result['dst']->getTagValues('a')[0][1]);
    }

    // -------------------------------------------------------------------------
    // setEntries()
    // -------------------------------------------------------------------------

    public function testCanSetEntries(): void
    {
        $rawEvent = [
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
            ->willReturn(Event::fromArray($rawEvent));

        $this->eventStore
            ->expects($this->once())
            ->method('publish')
            ->willReturn(PublishResult::ok());

        $folderCoord = new Coordinate(30045, self::VALID_PUBKEY, 'my-folder');
        $entry1 = new FolderEntry(new Coordinate(30040, self::VALID_PUBKEY, 'file1'));
        $entry2 = new FolderEntry(new Coordinate(30041, self::VALID_PUBKEY, 'file2'));

        $event = $this->service->setEntries($folderCoord, [$entry1, $entry2]);

        $aTags = $event->getTagValues('a');
        $this->assertCount(2, $aTags);
    }

    // -------------------------------------------------------------------------
    // archive()
    // -------------------------------------------------------------------------

    public function testCanArchiveFolder(): void
    {
        $this->eventStore
            ->expects($this->once())
            ->method('publish')
            ->with($this->callback(function (Event $event) {
                foreach ($event->tags as $tag) {
                    if ($tag[0] === 'status' && $tag[1] === 'archived') {
                        return true;
                    }
                }
                return false;
            }))
            ->willReturn(PublishResult::ok());

        $coordinate = new Coordinate(30045, self::VALID_PUBKEY, 'my-folder');
        $folder = new Folder($coordinate);

        $result = $this->service->archive($folder);

        $this->assertTrue($result->isSuccess());
    }
}
