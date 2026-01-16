<?php

declare(strict_types=1);

namespace DecentNewsroom\NostrDrive\Domain;

/**
 * Represents an entry within a Folder
 * Contains references to events (either by event ID or addressable coordinate)
 */
final class FolderEntry
{
    public function __construct(
        private string $eventId,
        private int $kind,
        private int $position,
        private ?string $pubkey = null,
        private ?string $identifier = null
    ) {
    }

    public function getEventId(): string
    {
        return $this->eventId;
    }

    public function getKind(): int
    {
        return $this->kind;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;
        return $this;
    }

    public function getPubkey(): ?string
    {
        return $this->pubkey;
    }

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    /**
     * Check if this is an addressable event (has coordinate)
     */
    public function isAddressable(): bool
    {
        return $this->pubkey !== null && $this->identifier !== null;
    }

    /**
     * Get the coordinate string (kind:pubkey:d-tag)
     */
    public function toCoordinate(): ?string
    {
        if (!$this->isAddressable()) {
            return null;
        }
        return "{$this->kind}:{$this->pubkey}:{$this->identifier}";
    }
}
