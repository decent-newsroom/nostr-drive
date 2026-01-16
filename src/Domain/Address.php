<?php

declare(strict_types=1);

namespace DecentNewsroom\NostrDrive\Domain;

/**
 * Represents a Nostr address (pubkey + optional relay hints)
 */
final readonly class Address
{
    public function __construct(
        private string $pubkey,
        private array $relays = []
    ) {
    }

    public function getPubkey(): string
    {
        return $this->pubkey;
    }

    public function getRelays(): array
    {
        return $this->relays;
    }

    public function toString(): string
    {
        return $this->pubkey;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
