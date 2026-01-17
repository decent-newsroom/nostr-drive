<?php

declare(strict_types=1);

namespace DecentNewsroom\NostrDrive\Validation;

use DecentNewsroom\NostrDrive\Exception\InvalidKindException;

/**
 * Validator for folder entry kinds
 * Ensures only allowed event kinds can be added to folders
 */
final class KindValidator
{
    /**
     * Allowed event kinds for folder entries
     * 
     * 30040 - Index
     * 30041 - AsciiDoc content
     * 30024 - Markdown article
     * 30023 - Markdown draft
     * 30045 - Folder (allows nesting)
     * 31924 - Calendar (NIP-52)
     * 31923 - Time-based calendar event (NIP-52)
     * 31922 - Date-based calendar event (NIP-52)
     */
    private const ALLOWED_KINDS = [
        30040,
        30041,
        30024,
        30023,
        30045, // Allow folder nesting
        31924,
        31923,
        31922,
    ];

    /**
     * Validate if a kind is allowed in folder entries
     *
     * @param int $kind The event kind to validate
     * @throws InvalidKindException If the kind is not allowed
     */
    public static function validate(int $kind): void
    {
        if (!in_array($kind, self::ALLOWED_KINDS, true)) {
            throw new InvalidKindException(
                sprintf(
                    'Kind %d is not allowed. Allowed kinds are: %s',
                    $kind,
                    implode(', ', self::ALLOWED_KINDS)
                )
            );
        }
    }

    /**
     * Check if a kind is allowed
     *
     * @param int $kind The event kind to check
     * @return bool True if the kind is allowed
     */
    public static function isAllowed(int $kind): bool
    {
        return in_array($kind, self::ALLOWED_KINDS, true);
    }

    /**
     * Get all allowed kinds
     *
     * @return array<int> Array of allowed event kinds
     */
    public static function getAllowedKinds(): array
    {
        return self::ALLOWED_KINDS;
    }
}
