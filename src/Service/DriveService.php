<?php

declare(strict_types=1);

namespace DecentNewsroom\NostrDrive\Service;

use DecentNewsroom\NostrDrive\Contract\EventStoreInterface;
use DecentNewsroom\NostrDrive\Domain\Address;
use DecentNewsroom\NostrDrive\Domain\Drive;
use DecentNewsroom\NostrDrive\Exception\NotFoundException;
use DecentNewsroom\NostrDrive\Exception\ValidationException;

/**
 * Service for managing Drive entities (kind:30042)
 * Provides CRUD operations for drives
 */
final class DriveService
{
    public function __construct(
        private readonly EventStoreInterface $eventStore
    ) {
    }

    /**
     * Create a new drive
     *
     * @param Address $address The address that owns the drive
     * @param string $identifier The d-tag identifier for the drive
     * @param string $name The drive name
     * @param array $tags Additional tags
     * @return Drive The created drive
     * @throws ValidationException If validation fails
     */
    public function create(
        Address $address,
        string $identifier,
        string $name,
        array $tags = []
    ): Drive {
        if (empty($identifier)) {
            throw new ValidationException('Drive identifier cannot be empty');
        }

        if (empty($name)) {
            throw new ValidationException('Drive name cannot be empty');
        }

        $drive = new Drive($address, $identifier, $name, $tags);

        // Publish to event store
        $event = $this->driveToEvent($drive);
        $this->eventStore->publish($event);

        return $drive;
    }

    /**
     * Get a drive by address and identifier
     *
     * @param Address $address The address
     * @param string $identifier The d-tag identifier
     * @return Drive The drive
     * @throws NotFoundException If drive not found
     */
    public function get(Address $address, string $identifier): Drive
    {
        $event = $this->eventStore->getLatestByAddress($address, Drive::KIND, $identifier);

        if ($event === null) {
            throw new NotFoundException("Drive not found for identifier: {$identifier}");
        }

        return $this->eventToDrive($event);
    }

    /**
     * Get a drive by event ID
     *
     * @param string $eventId The event ID
     * @return Drive The drive
     * @throws NotFoundException If drive not found
     */
    public function getById(string $eventId): Drive
    {
        $event = $this->eventStore->getById($eventId);

        if ($event === null || ($event['kind'] ?? null) !== Drive::KIND) {
            throw new NotFoundException("Drive not found with ID: {$eventId}");
        }

        return $this->eventToDrive($event);
    }

    /**
     * Update an existing drive
     *
     * @param Drive $drive The drive to update
     * @return Drive The updated drive
     * @throws ValidationException If validation fails
     */
    public function update(Drive $drive): Drive
    {
        if (empty($drive->getName())) {
            throw new ValidationException('Drive name cannot be empty');
        }

        $event = $this->driveToEvent($drive);
        $this->eventStore->publish($event);

        return $drive;
    }

    /**
     * Delete a drive (publish a deletion event)
     *
     * @param Drive $drive The drive to delete
     * @return bool True if successful
     */
    public function delete(Drive $drive): bool
    {
        $event = $this->driveToEvent($drive);
        $event['content'] = '';
        $event['tags'][] = ['deleted', 'true'];

        return $this->eventStore->publish($event);
    }

    /**
     * Convert a Drive domain object to an event array
     *
     * @param Drive $drive
     * @return array
     */
    private function driveToEvent(Drive $drive): array
    {
        $tags = [
            ['d', $drive->getIdentifier()],
            ['name', $drive->getName()],
        ];

        foreach ($drive->getTags() as $tag) {
            $tags[] = $tag;
        }

        return [
            'id' => $drive->getId(),
            'kind' => Drive::KIND,
            'pubkey' => $drive->getAddress()->getPubkey(),
            'created_at' => $drive->getCreatedAt(),
            'content' => '',
            'tags' => $tags,
        ];
    }

    /**
     * Convert an event array to a Drive domain object
     *
     * @param array $event
     * @return Drive
     */
    private function eventToDrive(array $event): Drive
    {
        $identifier = '';
        $name = '';
        $tags = [];

        foreach ($event['tags'] ?? [] as $tag) {
            if ($tag[0] === 'd') {
                $identifier = $tag[1] ?? '';
            } elseif ($tag[0] === 'name') {
                $name = $tag[1] ?? '';
            } else {
                $tags[] = $tag;
            }
        }

        $address = new Address($event['pubkey'] ?? '', []);
        $drive = new Drive($address, $identifier, $name, $tags);

        if (isset($event['id'])) {
            $drive->setId($event['id']);
        }

        if (isset($event['created_at'])) {
            $drive->setCreatedAt($event['created_at']);
            $drive->setUpdatedAt($event['created_at']);
        }

        return $drive;
    }
}
