<?php

declare(strict_types=1);

namespace DecentNewsroom\NostrDrive\Tests\Service;

use DecentNewsroom\NostrDrive\Contract\EventStoreInterface;
use DecentNewsroom\NostrDrive\Domain\Address;
use DecentNewsroom\NostrDrive\Domain\Folder;
use DecentNewsroom\NostrDrive\Exception\InvalidKindException;
use DecentNewsroom\NostrDrive\Exception\NotFoundException;
use DecentNewsroom\NostrDrive\Exception\ValidationException;
use DecentNewsroom\NostrDrive\Service\FolderService;
use PHPUnit\Framework\TestCase;

class FolderServiceTest extends TestCase
{
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

        $address = new Address('pubkey123');
        $folder = $this->service->create($address, 'my-folder', 'My Folder');

        $this->assertSame('pubkey123', $folder->getAddress()->getPubkey());
        $this->assertSame('my-folder', $folder->getIdentifier());
        $this->assertSame('My Folder', $folder->getName());
    }

    public function testCreateThrowsExceptionForEmptyIdentifier(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Folder identifier cannot be empty');

        $address = new Address('pubkey123');
        $this->service->create($address, '', 'My Folder');
    }

    public function testCreateThrowsExceptionForEmptyName(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Folder name cannot be empty');

        $address = new Address('pubkey123');
        $this->service->create($address, 'id', '');
    }

    public function testCanGetFolder(): void
    {
        $event = [
            'id' => 'event123',
            'kind' => 30045,
            'pubkey' => 'pubkey123',
            'created_at' => 1234567890,
            'content' => '{}',
            'tags' => [
                ['d', 'my-folder'],
                ['name', 'My Folder'],
            ],
        ];

        $this->eventStore
            ->expects($this->once())
            ->method('getLatestByAddress')
            ->willReturn($event);

        $address = new Address('pubkey123');
        $folder = $this->service->get($address, 'my-folder');

        $this->assertSame('my-folder', $folder->getIdentifier());
        $this->assertSame('My Folder', $folder->getName());
    }

    public function testGetThrowsNotFoundExceptionWhenFolderDoesNotExist(): void
    {
        $this->expectException(NotFoundException::class);

        $this->eventStore
            ->expects($this->once())
            ->method('getLatestByAddress')
            ->willReturn(null);

        $address = new Address('pubkey123');
        $this->service->get($address, 'non-existent');
    }

    public function testCanAddEntryToFolder(): void
    {
        $this->eventStore
            ->expects($this->exactly(2))
            ->method('publish')
            ->willReturn(true);

        $address = new Address('pubkey123');
        $folder = $this->service->create($address, 'my-folder', 'My Folder');

        $folder = $this->service->addEntry($folder, 'event123', 30040);

        $entries = $folder->getEntries();
        $this->assertCount(1, $entries);
        $this->assertSame('event123', $entries[0]->getEventId());
        $this->assertSame(30040, $entries[0]->getKind());
    }

    public function testAddEntryThrowsExceptionForInvalidKind(): void
    {
        $this->expectException(InvalidKindException::class);

        $this->eventStore
            ->expects($this->once())
            ->method('publish')
            ->willReturn(true);

        $address = new Address('pubkey123');
        $folder = $this->service->create($address, 'my-folder', 'My Folder');

        $this->service->addEntry($folder, 'event123', 1); // Kind 1 is not allowed
    }

    public function testCanRemoveEntryFromFolder(): void
    {
        $this->eventStore
            ->expects($this->exactly(3))
            ->method('publish')
            ->willReturn(true);

        $address = new Address('pubkey123');
        $folder = $this->service->create($address, 'my-folder', 'My Folder');

        $folder = $this->service->addEntry($folder, 'event123', 30040);
        $folder = $this->service->removeEntry($folder, 'event123');

        $this->assertCount(0, $folder->getEntries());
    }

    public function testCanReorderEntries(): void
    {
        $this->eventStore
            ->expects($this->exactly(4))
            ->method('publish')
            ->willReturn(true);

        $address = new Address('pubkey123');
        $folder = $this->service->create($address, 'my-folder', 'My Folder');

        $folder = $this->service->addEntry($folder, 'event1', 30040);
        $folder = $this->service->addEntry($folder, 'event2', 30041);

        // Reorder: event2 first, event1 second
        $folder = $this->service->reorderEntries($folder, ['event2', 'event1']);

        $entries = $folder->getEntries();
        $this->assertSame('event2', $entries[0]->getEventId());
        $this->assertSame(0, $entries[0]->getPosition());
        $this->assertSame('event1', $entries[1]->getEventId());
        $this->assertSame(1, $entries[1]->getPosition());
    }

    public function testReorderEntriesThrowsExceptionForInvalidEventId(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Event ID invalid not found in folder');

        $this->eventStore
            ->expects($this->exactly(2))
            ->method('publish')
            ->willReturn(true);

        $address = new Address('pubkey123');
        $folder = $this->service->create($address, 'my-folder', 'My Folder');

        $folder = $this->service->addEntry($folder, 'event1', 30040);

        $this->service->reorderEntries($folder, ['invalid', 'event1']);
    }
}
