<?php

declare(strict_types=1);

namespace DecentNewsroom\NostrDrive\Contract;

use DecentNewsroom\NostrDrive\Domain\Address;

/**
 * Interface for interacting with the Nostr event store
 */
interface EventStoreInterface
{
    /**
     * Get the latest event for a given address and kind
     *
     * @param Address $address The address to query
     * @param int $kind The event kind
     * @param string|null $identifier The d-tag identifier for replaceable events
     * @return array|null The event data or null if not found
     */
    public function getLatestByAddress(Address $address, int $kind, ?string $identifier = null): ?array;

    /**
     * Get an event by its ID
     *
     * @param string $eventId The event ID
     * @return array|null The event data or null if not found
     */
    public function getById(string $eventId): ?array;

    /**
     * Get the latest events for multiple addresses
     *
     * @param array<Address> $addresses Array of addresses to query
     * @param int $kind The event kind
     * @return array Array of event data indexed by address pubkey
     */
    public function getLatestByAddresses(array $addresses, int $kind): array;

    /**
     * Publish an event to the Nostr network
     *
     * @param array $event The event data to publish
     * @return bool True if successfully published
     */
    public function publish(array $event): bool;
}
