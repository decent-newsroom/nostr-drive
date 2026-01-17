<?php

declare(strict_types=1);

namespace DecentNewsroom\NostrDrive\Tests\Service;

use DecentNewsroom\NostrDrive\Contract\EventStoreInterface;
use DecentNewsroom\NostrDrive\Domain\Coordinate;
use DecentNewsroom\NostrDrive\Exception\NotFoundException;
use DecentNewsroom\NostrDrive\Exception\ValidationException;
use DecentNewsroom\NostrDrive\Service\DriveService;
use PHPUnit\Framework\TestCase;

class DriveServiceTest extends TestCase
{
    private const VALID_PUBKEY = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

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

        $coordinate = new Coordinate(30042, self::VALID_PUBKEY, 'my-drive');
        $drive = $this->service->create($coordinate, [], 'My Drive', 'Description');

        $this->assertTrue($drive->getCoordinate()->equals($coordinate));
        $this->assertSame('My Drive', $drive->getTitle());
        $this->assertSame('Description', $drive->getDescription());
    }

    public function testCanCreateDriveWithRoots(): void
    {
        $this->eventStore
            ->expects($this->once())
            ->method('publish')
            ->willReturn(true);

        $coordinate = new Coordinate(30042, self::VALID_PUBKEY, 'my-drive');
        $root1 = new Coordinate(30045, self::VALID_PUBKEY, 'themes');
        $root2 = new Coordinate(30045, self::VALID_PUBKEY, 'magazines');

        $drive = $this->service->create($coordinate, [$root1, $root2], 'My Drive');

        $roots = $drive->getRoots();
        $this->assertCount(2, $roots);
        $this->assertTrue($roots[0]->equals($root1));
        $this->assertTrue($roots[1]->equals($root2));
    }

    public function testCreateThrowsExceptionForInvalidKind(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Drive coordinate must be kind 30042');

        $invalidCoordinate = new Coordinate(30045, self::VALID_PUBKEY, 'wrong');
        $this->service->create($invalidCoordinate);
    }

    public function testCreateThrowsExceptionForInvalidRootKind(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Root folder coordinates must be kind 30045');

        $coordinate = new Coordinate(30042, self::VALID_PUBKEY, 'my-drive');
        $invalidRoot = new Coordinate(30042, self::VALID_PUBKEY, 'not-folder');

        $this->service->create($coordinate, [$invalidRoot]);
    }

    public function testCanGetDrive(): void
    {
        $event = [
            'id' => 'event123',
            'kind' => 30042,
            'pubkey' => self::VALID_PUBKEY,
            'created_at' => 1234567890,
            'content' => '',
            'tags' => [
                ['d', 'my-drive'],
                ['title', 'My Drive'],
                ['description', 'Test drive'],
                ['a', '30045:' . self::VALID_PUBKEY . ':themes'],
                ['a', '30045:' . self::VALID_PUBKEY . ':magazines'],
            ],
        ];

        $this->eventStore
            ->expects($this->once())
            ->method('getLatestByCoordinate')
            ->willReturn($event);

        $coordinate = new Coordinate(30042, self::VALID_PUBKEY, 'my-drive');
        $drive = $this->service->get($coordinate);

        $this->assertSame('My Drive', $drive->getTitle());
        $this->assertSame('Test drive', $drive->getDescription());
        $this->assertCount(2, $drive->getRoots());
    }

    public function testGetThrowsNotFoundExceptionWhenDriveDoesNotExist(): void
    {
        $this->expectException(NotFoundException::class);

        $this->eventStore
            ->expects($this->once())
            ->method('getLatestByCoordinate')
            ->willReturn(null);

        $coordinate = new Coordinate(30042, self::VALID_PUBKEY, 'non-existent');
        $this->service->get($coordinate);
    }

    public function testCanUpdateDrive(): void
    {
        $event = [
            'id' => 'event123',
            'kind' => 30042,
            'pubkey' => self::VALID_PUBKEY,
            'created_at' => 1234567890,
            'content' => '',
            'tags' => [
                ['d', 'my-drive'],
                ['title', 'Old Title'],
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

        $coordinate = new Coordinate(30042, self::VALID_PUBKEY, 'my-drive');
        $drive = $this->service->get($coordinate);
        $drive->setTitle('New Title');

        $updatedDrive = $this->service->update($drive);

        $this->assertSame('New Title', $updatedDrive->getTitle());
    }

    public function testCanSetRoots(): void
    {
        $event = [
            'id' => 'event123',
            'kind' => 30042,
            'pubkey' => self::VALID_PUBKEY,
            'created_at' => 1234567890,
            'content' => '',
            'tags' => [
                ['d', 'my-drive'],
                ['title', 'My Drive'],
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

        $coordinate = new Coordinate(30042, self::VALID_PUBKEY, 'my-drive');
        $root1 = new Coordinate(30045, self::VALID_PUBKEY, 'themes');
        $root2 = new Coordinate(30045, self::VALID_PUBKEY, 'magazines');

        $drive = $this->service->setRoots($coordinate, [$root1, $root2]);

        $this->assertCount(2, $drive->getRoots());
    }

    public function testCanArchiveDrive(): void
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

        $coordinate = new Coordinate(30042, self::VALID_PUBKEY, 'my-drive');
        $drive = new \DecentNewsroom\NostrDrive\Domain\Drive($coordinate);

        $result = $this->service->archive($drive);

        $this->assertTrue($result);
    }
}
