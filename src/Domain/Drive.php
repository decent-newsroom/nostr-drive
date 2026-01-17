<?php

declare(strict_types=1);

namespace DecentNewsroom\NostrDrive\Domain;

/**
 * Represents a Drive event (kind:30042)
 * A drive is the root container that mounts folder roots
 */
final class Drive
{
    public const KIND = 30042;

    private ?string $eventId = null;
    private int $createdAt;

    /**
     * @param Coordinate $coordinate The drive's coordinate (must be kind 30042)
     * @param Coordinate[] $roots Array of root folder coordinates (kind 30045)
     * @param string|null $title Drive title
     * @param string|null $description Drive description
     * @param array $rawEvent The raw Nostr event
     */
    public function __construct(
        private Coordinate $coordinate,
        private array $roots = [],
        private ?string $title = null,
        private ?string $description = null,
        private array $rawEvent = []
    ) {
        if ($coordinate->getKind() !== self::KIND) {
            throw new \InvalidArgumentException(
                "Drive coordinate must be kind " . self::KIND . ", got {$coordinate->getKind()}"
            );
        }

        // Validate roots are all kind 30045
        foreach ($roots as $root) {
            if (!$root instanceof Coordinate) {
                throw new \InvalidArgumentException('All roots must be Coordinate instances');
            }
            if ($root->getKind() !== Folder::KIND) {
                throw new \InvalidArgumentException(
                    "Root folder coordinates must be kind " . Folder::KIND . ", got {$root->getKind()}"
                );
            }
        }

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

    /**
     * @return Coordinate[]
     */
    public function getRoots(): array
    {
        return $this->roots;
    }

    public function setRoots(array $roots): self
    {
        // Validate roots are all kind 30045
        foreach ($roots as $root) {
            if (!$root instanceof Coordinate) {
                throw new \InvalidArgumentException('All roots must be Coordinate instances');
            }
            if ($root->getKind() !== Folder::KIND) {
                throw new \InvalidArgumentException(
                    "Root folder coordinates must be kind " . Folder::KIND . ", got {$root->getKind()}"
                );
            }
        }
        $this->roots = $roots;
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
