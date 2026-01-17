<?php

declare(strict_types=1);

namespace DecentNewsroom\NostrDrive\Contract;

use DecentNewsroom\NostrDrive\Domain\Coordinate;

/**
 * Interface for interacting with the Nostr event store
 */
interface EventStoreInterface
{
    /**
     * Get the latest event for a given coordinate
     *
     * @param Coordinate $coordinate The coordinate to query
     * @return array|null The event data or null if not found
     */
    public function getLatestByCoordinate(Coordinate $coordinate): ?array;

    /**
     * Get the latest events for multiple coordinates
     *
     * @param Coordinate[] $coordinates Array of coordinates to query
     * @return array Array of event data indexed by coordinate string
     */
    public function getLatestByCoordinates(array $coordinates): array;

    /**
     * Get an event by its ID
     *
     * @param string $eventId The event ID
     * @return array|null The event data or null if not found
     */
    public function getById(string $eventId): ?array;


    /**
     * Publish an event to the Nostr network
     *
     * @param array $event The event data to publish
     * @return bool True if successfully published
     */
    public function publish(array $event): bool;
}
