<?php

declare(strict_types=1);

namespace DecentNewsroom\NostrDrive\Contract;

use DecentNewsroom\NostrDrive\Domain\Coordinate;
use DecentNewsroom\NostrDrive\Domain\Event;
use DecentNewsroom\NostrDrive\Domain\PublishResult;

/**
 * Interface for interacting with the Nostr event store
 */
interface EventStoreInterface
{
    /**
     * Get the latest event for a given coordinate
     *
     * @param Coordinate $coordinate The coordinate to query
     * @return Event|null The event or null if not found
     */
    public function getLatestByCoordinate(Coordinate $coordinate): ?Event;

    /**
     * Get the latest events for multiple coordinates
     *
     * @param Coordinate[] $coordinates Array of coordinates to query
     * @return array<string, Event> Events indexed by coordinate string
     */
    public function getLatestByCoordinates(array $coordinates): array;

    /**
     * Get an event by its ID
     *
     * @param string $eventId The event ID
     * @return Event|null The event or null if not found
     */
    public function getById(string $eventId): ?Event;

    /**
     * Publish an event to the Nostr network
     *
     * @param Event $event The event to publish
     * @return PublishResult The publish result
     */
    public function publish(Event $event): PublishResult;
}
