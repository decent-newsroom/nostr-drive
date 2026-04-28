<?php

declare(strict_types=1);

namespace DecentNewsroom\NostrDrive\Domain;

/**
 * Represents a Nostr event DTO
 * Encapsulates the raw event data (id, kind, pubkey, created_at, tags, content, sig)
 */
final readonly class Event
{
    public function __construct(
        public int $kind,
        public string $pubkey,
        public int $createdAt,
        public array $tags,
        public string $content = '',
        public ?string $id = null,
        public ?string $sig = null
    ) {
    }

    /**
     * Create an Event from a raw array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            kind: (int) ($data['kind'] ?? 0),
            pubkey: (string) ($data['pubkey'] ?? ''),
            createdAt: (int) ($data['created_at'] ?? 0),
            tags: (array) ($data['tags'] ?? []),
            content: (string) ($data['content'] ?? ''),
            id: isset($data['id']) && $data['id'] !== null ? (string) $data['id'] : null,
            sig: isset($data['sig']) && $data['sig'] !== null ? (string) $data['sig'] : null
        );
    }

    /**
     * Convert to a raw array
     */
    public function toArray(): array
    {
        $data = [
            'kind' => $this->kind,
            'pubkey' => $this->pubkey,
            'created_at' => $this->createdAt,
            'content' => $this->content,
            'tags' => $this->tags,
        ];

        if ($this->id !== null) {
            $data['id'] = $this->id;
        }

        if ($this->sig !== null) {
            $data['sig'] = $this->sig;
        }

        return $data;
    }

    /**
     * Get a tag value by tag name (returns first match)
     */
    public function getTagValue(string $name): ?string
    {
        foreach ($this->tags as $tag) {
            if (isset($tag[0]) && $tag[0] === $name) {
                return $tag[1] ?? null;
            }
        }
        return null;
    }

    /**
     * Get all tag values by tag name
     *
     * @return array<array>
     */
    public function getTagValues(string $name): array
    {
        $result = [];
        foreach ($this->tags as $tag) {
            if (isset($tag[0]) && $tag[0] === $name) {
                $result[] = $tag;
            }
        }
        return $result;
    }
}

