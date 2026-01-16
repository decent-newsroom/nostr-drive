<?php

declare(strict_types=1);

namespace DecentNewsroom\NostrDrive\Domain;

/**
 * Represents a Drive event (kind:30042)
 * A drive is the root container for organizing files and folders
 */
final class Drive
{
    public const KIND = 30042;

    private ?string $id = null;
    private int $createdAt;
    private int $updatedAt;

    public function __construct(
        private Address $address,
        private string $identifier,
        private string $name,
        private array $tags = []
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
