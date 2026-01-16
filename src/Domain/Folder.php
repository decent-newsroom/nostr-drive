<?php

declare(strict_types=1);

namespace DecentNewsroom\NostrDrive\Domain;

/**
 * Represents a Folder event (kind:30045)
 * A folder can contain entries of allowed kinds
 */
final class Folder
{
    public const KIND = 30045;

    private ?string $id = null;
    private int $createdAt;
    private int $updatedAt;
    private array $entries = [];

    public function __construct(
        private Address $address,
        private string $identifier,
        private string $name,
        private array $tags = [],
        private array $metadata = []
    ) {
        $this->createdAt = time();
        $this->updatedAt = time();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getAddress(): Address
    {
        return $this->address;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        $this->updatedAt = time();
        return $this;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function setTags(array $tags): self
    {
        $this->tags = $tags;
        $this->updatedAt = time();
        return $this;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;
        $this->updatedAt = time();
        return $this;
    }

    public function getEntries(): array
    {
        return $this->entries;
    }

    public function setEntries(array $entries): self
    {
        $this->entries = $entries;
        $this->updatedAt = time();
        return $this;
    }

    public function addEntry(FolderEntry $entry): self
    {
        $this->entries[] = $entry;
        $this->updatedAt = time();
        return $this;
    }

    public function removeEntry(string $eventId): self
    {
        $this->entries = array_values(array_filter(
            $this->entries,
            fn(FolderEntry $entry) => $entry->getEventId() !== $eventId
        ));
        $this->updatedAt = time();
        return $this;
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

    public function getUpdatedAt(): int
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(int $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getKind(): int
    {
        return self::KIND;
    }
}
