<?php

declare(strict_types=1);

namespace DecentNewsroom\NostrDrive\Tests\Domain;

use DecentNewsroom\NostrDrive\Domain\Coordinate;
use DecentNewsroom\NostrDrive\Domain\Drive;
use PHPUnit\Framework\TestCase;

class DriveTest extends TestCase
{
    private const VALID_PUBKEY = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    public function testCanCreateDrive(): void
    {
        $coordinate = new Coordinate(30042, self::VALID_PUBKEY, 'my-drive');
        $title = 'My Drive';
        $description = 'A test drive';

        $drive = new Drive($coordinate, [], $title, $description);

        $this->assertSame($coordinate, $drive->getCoordinate());
        $this->assertSame($title, $drive->getTitle());
        $this->assertSame($description, $drive->getDescription());
        $this->assertSame(Drive::KIND, $drive->getKind());
        $this->assertSame(30042, $drive->getKind());
    }

    public function testCanCreateDriveWithRoots(): void
    {
        $coordinate = new Coordinate(30042, self::VALID_PUBKEY, 'my-drive');
        $root1 = new Coordinate(30045, self::VALID_PUBKEY, 'themes');
        $root2 = new Coordinate(30045, self::VALID_PUBKEY, 'magazines');

        $drive = new Drive($coordinate, [$root1, $root2]);

        $roots = $drive->getRoots();
        $this->assertCount(2, $roots);
        $this->assertSame($root1, $roots[0]);
        $this->assertSame($root2, $roots[1]);
    }

    public function testCanSetAndGetTitle(): void
    {
        $coordinate = new Coordinate(30042, self::VALID_PUBKEY, 'my-drive');
        $drive = new Drive($coordinate, [], 'Old Title');

        $drive->setTitle('New Title');

        $this->assertSame('New Title', $drive->getTitle());
    }

    public function testCanSetAndGetDescription(): void
    {
        $coordinate = new Coordinate(30042, self::VALID_PUBKEY, 'my-drive');
        $drive = new Drive($coordinate);

        $drive->setDescription('New Description');

        $this->assertSame('New Description', $drive->getDescription());
    }

    public function testCanSetRoots(): void
    {
        $coordinate = new Coordinate(30042, self::VALID_PUBKEY, 'my-drive');
        $drive = new Drive($coordinate);

        $root1 = new Coordinate(30045, self::VALID_PUBKEY, 'themes');
        $root2 = new Coordinate(30045, self::VALID_PUBKEY, 'magazines');

        $drive->setRoots([$root1, $root2]);

        $roots = $drive->getRoots();
        $this->assertCount(2, $roots);
        $this->assertTrue($roots[0]->equals($root1));
        $this->assertTrue($roots[1]->equals($root2));
    }

    public function testTimestampsAreSet(): void
    {
        $coordinate = new Coordinate(30042, self::VALID_PUBKEY, 'my-drive');
        $drive = new Drive($coordinate);

        $this->assertGreaterThan(0, $drive->getCreatedAt());
    }

    public function testThrowsOnInvalidKind(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Drive coordinate must be kind 30042');

        $invalidCoordinate = new Coordinate(30045, self::VALID_PUBKEY, 'wrong-kind');
        new Drive($invalidCoordinate);
    }

    public function testThrowsOnInvalidRootKind(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Root folder coordinates must be kind 30045');

        $coordinate = new Coordinate(30042, self::VALID_PUBKEY, 'my-drive');
        $invalidRoot = new Coordinate(30042, self::VALID_PUBKEY, 'wrong-kind');

        new Drive($coordinate, [$invalidRoot]);
    }

    public function testSetRootsThrowsOnInvalidKind(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Root folder coordinates must be kind 30045');

        $coordinate = new Coordinate(30042, self::VALID_PUBKEY, 'my-drive');
        $drive = new Drive($coordinate);
        $invalidRoot = new Coordinate(30040, self::VALID_PUBKEY, 'not-a-folder');

        $drive->setRoots([$invalidRoot]);
    }
}
