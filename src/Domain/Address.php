<?php

declare(strict_types=1);

namespace DecentNewsroom\NostrDrive\Domain;

/**
 * Represents a Nostr address
 * Can be either:
 * - A pubkey (hex string)
 * - A coordinate (kind:pubkey:d-tag for addressable events)
 */
final readonly class Address
{
    public function __construct(
        private string $pubkey,
        private array $relays = [],
        private ?int $kind = null,
        private ?string $identifier = null
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

    public function getKind(): ?int
    {
        return $this->kind;
    }

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    /**
     * Check if this is a coordinate address (kind:pubkey:d-tag)
     */
    public function isCoordinate(): bool
    {
        return $this->kind !== null && $this->identifier !== null;
    }

    /**
     * Get the coordinate string (kind:pubkey:d-tag)
     */
    public function toCoordinate(): ?string
    {
        if (!$this->isCoordinate()) {
            return null;
        }
        return "{$this->kind}:{$this->pubkey}:{$this->identifier}";
    }

    public function toString(): string
    {
        return $this->isCoordinate() ? $this->toCoordinate() : $this->pubkey;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
