<?php

declare(strict_types=1);

namespace DecentNewsroom\NostrDrive\Tests\Service;

use DecentNewsroom\NostrDrive\Contract\EventStoreInterface;
use DecentNewsroom\NostrDrive\Domain\Address;
use DecentNewsroom\NostrDrive\Exception\NotFoundException;
use DecentNewsroom\NostrDrive\Exception\ValidationException;
use DecentNewsroom\NostrDrive\Service\DriveService;
use PHPUnit\Framework\TestCase;

class DriveServiceTest extends TestCase
{
    private EventStoreInterface $eventStore;
    private DriveService $service;

    protected function setUp(): void
    {
        $this->eventStore = $this->createMock(EventStoreInterface::class);
        $this->service = new DriveService($this->eventStore);
    }

    public function testCanCreateDrive(): void
    {
        $this->eventStore
            ->expects($this->once())
            ->method('publish')
            ->willReturn(true);

        $address = new Address('pubkey123');
        $drive = $this->service->create($address, 'my-drive', 'My Drive');

        $this->assertSame('pubkey123', $drive->getAddress()->getPubkey());
        $this->assertSame('my-drive', $drive->getIdentifier());
        $this->assertSame('My Drive', $drive->getName());
    }

    public function testCreateThrowsExceptionForEmptyIdentifier(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Drive identifier cannot be empty');

        $address = new Address('pubkey123');
        $this->service->create($address, '', 'My Drive');
    }

    public function testCreateThrowsExceptionForEmptyName(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Drive name cannot be empty');

        $address = new Address('pubkey123');
        $this->service->create($address, 'id', '');
    }

    public function testCanGetDrive(): void
    {
        $event = [
            'id' => 'event123',
            'kind' => 30042,
            'pubkey' => 'pubkey123',
            'created_at' => 1234567890,
            'content' => '{}',
            'tags' => [
                ['d', 'my-drive'],
                ['name', 'My Drive'],
            ],
        ];

        $this->eventStore
            ->expects($this->once())
            ->method('getLatestByAddress')
            ->willReturn($event);

        $address = new Address('pubkey123');
        $drive = $this->service->get($address, 'my-drive');

        $this->assertSame('my-drive', $drive->getIdentifier());
        $this->assertSame('My Drive', $drive->getName());
    }

    public function testGetThrowsNotFoundExceptionWhenDriveDoesNotExist(): void
    {
        $this->expectException(NotFoundException::class);

        $this->eventStore
            ->expects($this->once())
            ->method('getLatestByAddress')
            ->willReturn(null);

        $address = new Address('pubkey123');
        $this->service->get($address, 'non-existent');
    }

    public function testCanGetDriveById(): void
    {
        $event = [
            'id' => 'event123',
            'kind' => 30042,
            'pubkey' => 'pubkey123',
            'created_at' => 1234567890,
            'content' => '{}',
            'tags' => [
                ['d', 'my-drive'],
                ['name', 'My Drive'],
            ],
        ];

        $this->eventStore
            ->expects($this->once())
            ->method('getById')
            ->with('event123')
            ->willReturn($event);

        $drive = $this->service->getById('event123');

        $this->assertSame('event123', $drive->getId());
        $this->assertSame('my-drive', $drive->getIdentifier());
    }

    public function testCanUpdateDrive(): void
    {
        $this->eventStore
            ->expects($this->exactly(2))
            ->method('publish')
            ->willReturn(true);

        $address = new Address('pubkey123');
        $drive = $this->service->create($address, 'my-drive', 'Old Name');

        $drive->setName('New Name');
        $updatedDrive = $this->service->update($drive);

        $this->assertSame('New Name', $updatedDrive->getName());
    }

    public function testCanDeleteDrive(): void
    {
        $this->eventStore
            ->expects($this->exactly(2))
            ->method('publish')
            ->willReturn(true);

        $address = new Address('pubkey123');
        $drive = $this->service->create($address, 'my-drive', 'My Drive');

        $result = $this->service->delete($drive);

        $this->assertTrue($result);
    }
}
