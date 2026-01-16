<?php

declare(strict_types=1);

namespace DecentNewsroom\NostrDrive\Tests\Domain;

use DecentNewsroom\NostrDrive\Domain\Address;
use DecentNewsroom\NostrDrive\Domain\Drive;
use PHPUnit\Framework\TestCase;

class DriveTest extends TestCase
{
    public function testCanCreateDrive(): void
    {
        $address = new Address('pubkey123');
        $identifier = 'my-drive';
        $name = 'My Drive';

        $drive = new Drive($address, $identifier, $name);

        $this->assertSame($address, $drive->getAddress());
        $this->assertSame($identifier, $drive->getIdentifier());
        $this->assertSame($name, $drive->getName());
        $this->assertSame(Drive::KIND, $drive->getKind());
        $this->assertSame(30042, $drive->getKind());
    }

    public function testCanSetAndGetName(): void
    {
        $address = new Address('pubkey123');
        $drive = new Drive($address, 'id', 'Old Name');

        $drive->setName('New Name');

        $this->assertSame('New Name', $drive->getName());
    }

    public function testCanSetAndGetTags(): void
    {
        $address = new Address('pubkey123');
        $drive = new Drive($address, 'id', 'Name');

        $tags = [['tag1', 'value1'], ['tag2', 'value2']];
        $drive->setTags($tags);

        $this->assertSame($tags, $drive->getTags());
    }

    public function testTimestampsAreSet(): void
    {
        $address = new Address('pubkey123');
        $drive = new Drive($address, 'id', 'Name');

        $this->assertGreaterThan(0, $drive->getCreatedAt());
        $this->assertGreaterThan(0, $drive->getUpdatedAt());
    }
}
