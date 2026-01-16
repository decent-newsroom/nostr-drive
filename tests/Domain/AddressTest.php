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
    }

    public function testCanCreateAddressWithoutRelays(): void
    {
        $pubkey = 'abc123';
        $address = new Address($pubkey);

        $this->assertSame($pubkey, $address->getPubkey());
        $this->assertSame([], $address->getRelays());
    }

    public function testToStringReturnsPublicKey(): void
    {
        $pubkey = 'abc123';
        $address = new Address($pubkey);

        $this->assertSame($pubkey, $address->toString());
        $this->assertSame($pubkey, (string) $address);
    }
}
