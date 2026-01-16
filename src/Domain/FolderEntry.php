<?php

declare(strict_types=1);

namespace DecentNewsroom\NostrDrive\Domain;

/**
 * Represents an entry within a Folder
 * Contains references to events of allowed kinds
 */
final class FolderEntry
{
    public function __construct(
        private string $eventId,
        private int $kind,
        private int $position,
        private array $metadata = []
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

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }
}
