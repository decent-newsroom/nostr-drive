<?php

declare(strict_types=1);

namespace DecentNewsroom\NostrDrive\Tests\Domain;

use DecentNewsroom\NostrDrive\Domain\Address;
use PHPUnit\Framework\TestCase;

class AddressTest extends TestCase
{
    public function testCanCreateAddress(): void
    {
        $pubkey = 'abc123';
        $relays = ['wss://relay1.com', 'wss://relay2.com'];

        $address = new Address($pubkey, $relays);

        $this->assertSame($pubkey, $address->getPubkey());
        $this->assertSame($relays, $address->getRelays());
        $this->assertFalse($address->isCoordinate());
    }

    public function testCanCreateAddressWithoutRelays(): void
    {
        $pubkey = 'abc123';
        $address = new Address($pubkey);

        $this->assertSame($pubkey, $address->getPubkey());
        $this->assertSame([], $address->getRelays());
        $this->assertFalse($address->isCoordinate());
    }

    public function testCanCreateCoordinateAddress(): void
    {
        $pubkey = 'abc123';
        $kind = 30042;
        $identifier = 'my-drive';

        $address = new Address($pubkey, [], $kind, $identifier);

        $this->assertSame($pubkey, $address->getPubkey());
        $this->assertSame($kind, $address->getKind());
        $this->assertSame($identifier, $address->getIdentifier());
        $this->assertTrue($address->isCoordinate());
        $this->assertSame("{$kind}:{$pubkey}:{$identifier}", $address->toCoordinate());
    }

    public function testToStringReturnsPublicKey(): void
    {
        $pubkey = 'abc123';
        $address = new Address($pubkey);

        $this->assertSame($pubkey, $address->toString());
        $this->assertSame($pubkey, (string) $address);
    }

    public function testToStringReturnsCoordinate(): void
    {
        $pubkey = 'abc123';
        $kind = 30042;
        $identifier = 'my-drive';
        $address = new Address($pubkey, [], $kind, $identifier);

        $expected = "{$kind}:{$pubkey}:{$identifier}";
        $this->assertSame($expected, $address->toString());
        $this->assertSame($expected, (string) $address);
    }
}
