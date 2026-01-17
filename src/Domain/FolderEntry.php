<?php

declare(strict_types=1);

namespace DecentNewsroom\NostrDrive\Domain;

/**
 * Represents an entry within a Folder
 * Uses coordinate (a-tag) as the canonical reference for addressable events
 */
final readonly class FolderEntry
{
    public function __construct(
        private Coordinate $coordinate,
        private ?string $relayHint = null,
        private ?string $lastSeenEventId = null,
        private ?string $nameHint = null
    ) {
    }

    public function getCoordinate(): Coordinate
    {
        return $this->coordinate;
    }

    public function getRelayHint(): ?string
    {
        return $this->relayHint;
    }

    public function getLastSeenEventId(): ?string
    {
        return $this->lastSeenEventId;
    }

    public function getNameHint(): ?string
    {
        return $this->nameHint;
    }

    /**
     * Create entry with updated hints (returns new instance due to readonly)
     */
    public function withHints(
        ?string $relayHint = null,
        ?string $lastSeenEventId = null,
        ?string $nameHint = null
    ): self {
        return new self(
            $this->coordinate,
            $relayHint ?? $this->relayHint,
            $lastSeenEventId ?? $this->lastSeenEventId,
            $nameHint ?? $this->nameHint
        );
    }
}
