<?php
declare(strict_types=1);
namespace DecentNewsroom\NostrDrive\Domain;
/**
 * Value object for Drive/Folder metadata (title and description)
 */
final readonly class Meta
{
    public function __construct(
        public ?string $title = null,
        public ?string $description = null
    ) {
    }
}
