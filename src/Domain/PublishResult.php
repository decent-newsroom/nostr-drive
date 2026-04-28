<?php

declare(strict_types=1);

namespace DecentNewsroom\NostrDrive\Domain;

/**
 * Value object representing the result of publishing a Nostr event
 */
final readonly class PublishResult
{
    public function __construct(
        public bool $success,
        public ?string $message = null
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public static function ok(?string $message = null): self
    {
        return new self(true, $message);
    }

    public static function fail(string $message = ''): self
    {
        return new self(false, $message);
    }
}
