<?php

declare(strict_types=1);

namespace DecentNewsroom\NostrDrive\Domain;

/**
 * Represents a Folder event (kind:30045)
 * A folder contains entries (coordinates) of allowed kinds
 */
final class Folder
{
    public const KIND = 30045;

    private ?string $eventId = null;
    private int $createdAt;
    private array $entries = [];

    /**
     * @param Coordinate $coordinate The folder's coordinate (must be kind 30045)
     * @param FolderEntry[] $entries Array of folder entries
     * @param string|null $title Folder title
     * @param string|null $description Folder description
     * @param array $rawEvent The raw Nostr event
     */
    public function __construct(
        private Coordinate $coordinate,
        array $entries = [],
        private ?string $title = null,
        private ?string $description = null,
        private array $rawEvent = []
    ) {
        if ($coordinate->getKind() !== self::KIND) {
            throw new \InvalidArgumentException(
                "Folder coordinate must be kind " . self::KIND . ", got {$coordinate->getKind()}"
            );
        }

        // Validate entries
        foreach ($entries as $entry) {
            if (!$entry instanceof FolderEntry) {
                throw new \InvalidArgumentException('All entries must be FolderEntry instances');
            }
        }
        $this->entries = $entries;

        $this->createdAt = $rawEvent['created_at'] ?? time();
    }

    public function getCoordinate(): Coordinate
    {
        return $this->coordinate;
    }

    public function getEventId(): ?string
    {
        return $this->eventId;
    }

    public function setEventId(string $eventId): self
    {
        $this->eventId = $eventId;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return FolderEntry[]
     */
    public function getEntries(): array
    {
        return $this->entries;
    }

    /**
     * @param FolderEntry[] $entries
     */
    public function setEntries(array $entries): self
    {
        // Validate entries
        foreach ($entries as $entry) {
            if (!$entry instanceof FolderEntry) {
                throw new \InvalidArgumentException('All entries must be FolderEntry instances');
            }
        }
        $this->entries = $entries;
        return $this;
    }

    public function addEntry(FolderEntry $entry): self
    {
        $this->entries[] = $entry;
        return $this;
    }

    /**
     * Remove entry by coordinate
     */
    public function removeEntry(Coordinate $coordinate): self
    {
        $this->entries = array_values(array_filter(
            $this->entries,
            fn(FolderEntry $entry) => !$entry->getCoordinate()->equals($coordinate)
        ));
        return $this;
    }

    /**
     * Check if folder contains an entry with the given coordinate
     */
    public function hasEntry(Coordinate $coordinate): bool
    {
        foreach ($this->entries as $entry) {
            if ($entry->getCoordinate()->equals($coordinate)) {
                return true;
            }
        }
        return false;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function setCreatedAt(int $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getRawEvent(): array
    {
        return $this->rawEvent;
    }

    public function getKind(): int
    {
        return self::KIND;
    }
}
