<?php

declare(strict_types=1);

namespace DecentNewsroom\NostrDrive\Tests\Service;

use DecentNewsroom\NostrDrive\Contract\EventStoreInterface;
use DecentNewsroom\NostrDrive\Domain\Coordinate;
use DecentNewsroom\NostrDrive\Domain\Drive;
use DecentNewsroom\NostrDrive\Domain\Event;
use DecentNewsroom\NostrDrive\Domain\Meta;
use DecentNewsroom\NostrDrive\Domain\PublishResult;
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
            ->willReturn(PublishResult::ok());

        $coordinate = new Coordinate(30042, self::VALID_PUBKEY, 'my-drive');
        $event = $this->service->create($coordinate, [], new Meta('My Drive', 'Description'));

        $this->assertSame(30042, $event->kind);
        $this->assertSame(self::VALID_PUBKEY, $event->pubkey);
        $this->assertSame('My Drive', $event->getTagValue('title'));
        $this->assertSame('Description', $event->getTagValue('description'));
        $this->assertSame('my-drive', $event->getTagValue('d'));
    }

    public function testCanCreateDriveWithRoots(): void
    {
        $this->eventStore
            ->expects($this->once())
            ->method('publish')
            ->willReturn(PublishResult::ok());

        $coordinate = new Coordinate(30042, self::VALID_PUBKEY, 'my-drive');
        $root1 = new Coordinate(30045, self::VALID_PUBKEY, 'themes');
        $root2 = new Coordinate(30045, self::VALID_PUBKEY, 'magazines');

        $event = $this->service->create($coordinate, [$root1, $root2], new Meta('My Drive'));

        // Assert `a` tags contain root folder coordinates in order
        $aTags = $event->getTagValues('a');
        $this->assertCount(2, $aTags);
        $this->assertSame($root1->toString(), $aTags[0][1]);
        $this->assertSame($root2->toString(), $aTags[1][1]);
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
        $rawEvent = [
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
            ->willReturn(Event::fromArray($rawEvent));

        $coordinate = new Coordinate(30042, self::VALID_PUBKEY, 'my-drive');
        $drive = $this->service->get($coordinate);

        $this->assertSame('My Drive', $drive->getTitle());
        $this->assertSame('Test drive', $drive->getDescription());
        $this->assertCount(2, $drive->getRoots());
    }

    public function testGetParsesRootCoordinatesFromATags(): void
    {
        $themes = new Coordinate(30045, self::VALID_PUBKEY, 'themes');
        $magazines = new Coordinate(30045, self::VALID_PUBKEY, 'magazines');
        $calendar = new Coordinate(30045, self::VALID_PUBKEY, 'calendar');

        $rawEvent = [
            'id' => 'event123',
            'kind' => 30042,
            'pubkey' => self::VALID_PUBKEY,
            'created_at' => 1234567890,
            'content' => '',
            'tags' => [
                ['d', 'my-drive'],
                ['title', 'My Drive'],
                ['a', $themes->toString(), 'wss://relay.example'],
                ['a', $magazines->toString(), 'wss://relay.example'],
                ['a', $calendar->toString(), 'wss://relay.example'],
            ],
        ];

        $this->eventStore
            ->method('getLatestByCoordinate')
            ->willReturn(Event::fromArray($rawEvent));

        $drive = $this->service->get(new Coordinate(30042, self::VALID_PUBKEY, 'my-drive'));

        $roots = $drive->getRoots();
        $this->assertCount(3, $roots);
        $this->assertTrue($roots[0]->equals($themes));
        $this->assertTrue($roots[1]->equals($magazines));
        $this->assertTrue($roots[2]->equals($calendar));
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

    public function testCanSetRoots(): void
    {
        $rawEvent = [
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
            ->willReturn(Event::fromArray($rawEvent));

        $this->eventStore
            ->expects($this->once())
            ->method('publish')
            ->willReturn(PublishResult::ok());

        $coordinate = new Coordinate(30042, self::VALID_PUBKEY, 'my-drive');
        $root1 = new Coordinate(30045, self::VALID_PUBKEY, 'themes');
        $root2 = new Coordinate(30045, self::VALID_PUBKEY, 'magazines');

        $event = $this->service->setRoots($coordinate, [$root1, $root2]);

        // Assert returned event contains the correct `a` tags for root mounts
        $aTags = $event->getTagValues('a');
        $this->assertCount(2, $aTags);
        $this->assertSame($root1->toString(), $aTags[0][1]);
        $this->assertSame($root2->toString(), $aTags[1][1]);
    }

    public function testCanArchiveDrive(): void
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

        $coordinate = new Coordinate(30042, self::VALID_PUBKEY, 'my-drive');
        $drive = new Drive($coordinate);

        $result = $this->service->archive($drive);

        $this->assertTrue($result->isSuccess());
    }
}
