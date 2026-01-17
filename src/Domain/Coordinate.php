<?php

declare(strict_types=1);

namespace DecentNewsroom\NostrDrive\Domain;

use InvalidArgumentException;

/**
 * Represents a Nostr coordinate (kind:pubkey:d-tag) for addressable events
 * This is the canonical identifier for replaceable/addressable events (30000-39999)
 */
final readonly class Coordinate
{
    public function __construct(
        private int $kind,
        private string $pubkey,
        private string $identifier
    ) {
        if ($this->kind < 30000 || $this->kind > 39999) {
            throw new InvalidArgumentException(
                "Kind {$this->kind} is not addressable (must be 30000-39999)"
            );
        }

        if (empty($this->pubkey)) {
            throw new InvalidArgumentException('Pubkey cannot be empty');
        }

        if (!preg_match('/^[0-9a-f]{64}$/i', $this->pubkey)) {
            throw new InvalidArgumentException('Pubkey must be a valid 64-character hex string');
        }

        // identifier can be empty string for some addressable events
    }

    public function getKind(): int
    {
        return $this->kind;
    }

    public function getPubkey(): string
    {
        return $this->pubkey;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * Parse a coordinate string (kind:pubkey:d-tag)
     */
    public static function parse(string $coordinate): self
    {
        $parts = explode(':', $coordinate, 3);

        if (count($parts) !== 3) {
            throw new InvalidArgumentException(
                "Invalid coordinate format. Expected 'kind:pubkey:d-tag', got: {$coordinate}"
            );
        }

        if (!is_numeric($parts[0])) {
            throw new InvalidArgumentException("Kind must be numeric in coordinate: {$coordinate}");
        }

        return new self(
            (int) $parts[0],
            $parts[1],
            $parts[2]
        );
    }

    /**
     * Convert to coordinate string (kind:pubkey:d-tag)
     */
    public function toString(): string
    {
        return "{$this->kind}:{$this->pubkey}:{$this->identifier}";
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Check equality with another coordinate
     */
    public function equals(Coordinate $other): bool
    {
        return $this->kind === $other->kind
            && $this->pubkey === $other->pubkey
            && $this->identifier === $other->identifier;
    }
}
