<?php

declare(strict_types=1);

namespace DecentNewsroom\NostrDrive\Service;

use DecentNewsroom\NostrDrive\Contract\EventStoreInterface;
use DecentNewsroom\NostrDrive\Domain\Address;
use DecentNewsroom\NostrDrive\Domain\Folder;
use DecentNewsroom\NostrDrive\Domain\FolderEntry;
use DecentNewsroom\NostrDrive\Exception\NotFoundException;
use DecentNewsroom\NostrDrive\Exception\ValidationException;
use DecentNewsroom\NostrDrive\Validation\KindValidator;

/**
 * Service for managing Folder entities (kind:30045)
 * Provides CRUD operations plus entry management (add/remove/reorder)
 */
final class FolderService
{
    public function __construct(
        private readonly EventStoreInterface $eventStore
    ) {
    }

    /**
     * Create a new folder
     *
     * @param Address $address The address that owns the folder
     * @param string $identifier The d-tag identifier for the folder
     * @param string $name The folder name
     * @param array $tags Additional tags
     * @return Folder The created folder
     * @throws ValidationException If validation fails
     */
    public function create(
        Address $address,
        string $identifier,
        string $name,
        array $tags = []
    ): Folder {
        if (empty($identifier)) {
            throw new ValidationException('Folder identifier cannot be empty');
        }

        if (empty($name)) {
            throw new ValidationException('Folder name cannot be empty');
        }

        $folder = new Folder($address, $identifier, $name, $tags);

        // Publish to event store
        $event = $this->folderToEvent($folder);
        $this->eventStore->publish($event);

        return $folder;
    }

    /**
     * Get a folder by address and identifier
     *
     * @param Address $address The address
     * @param string $identifier The d-tag identifier
     * @return Folder The folder
     * @throws NotFoundException If folder not found
     */
    public function get(Address $address, string $identifier): Folder
    {
        $event = $this->eventStore->getLatestByAddress($address, Folder::KIND, $identifier);

        if ($event === null) {
            throw new NotFoundException("Folder not found for identifier: {$identifier}");
        }

        return $this->eventToFolder($event);
    }

    /**
     * Get a folder by event ID
     *
     * @param string $eventId The event ID
     * @return Folder The folder
     * @throws NotFoundException If folder not found
     */
    public function getById(string $eventId): Folder
    {
        $event = $this->eventStore->getById($eventId);

        if ($event === null || ($event['kind'] ?? null) !== Folder::KIND) {
            throw new NotFoundException("Folder not found with ID: {$eventId}");
        }

        return $this->eventToFolder($event);
    }

    /**
     * Update an existing folder
     *
     * @param Folder $folder The folder to update
     * @return Folder The updated folder
     * @throws ValidationException If validation fails
     */
    public function update(Folder $folder): Folder
    {
        if (empty($folder->getName())) {
            throw new ValidationException('Folder name cannot be empty');
        }

        $event = $this->folderToEvent($folder);
        $this->eventStore->publish($event);

        return $folder;
    }

    /**
     * Delete a folder (publish a deletion event)
     *
     * @param Folder $folder The folder to delete
     * @return bool True if successful
     */
    public function delete(Folder $folder): bool
    {
        $event = $this->folderToEvent($folder);
        $event['content'] = '';
        $event['tags'][] = ['deleted', 'true'];

        return $this->eventStore->publish($event);
    }

    /**
     * Add an entry to a folder
     *
     * @param Folder $folder The folder
     * @param string $eventId The event ID to add
     * @param int $kind The event kind
     * @param string|null $pubkey The pubkey for addressable events (optional)
     * @param string|null $identifier The d-tag for addressable events (optional)
     * @return Folder The updated folder
     * @throws ValidationException If kind is not allowed
     */
    public function addEntry(
        Folder $folder,
        string $eventId,
        int $kind,
        ?string $pubkey = null,
        ?string $identifier = null
    ): Folder {
        // Validate the kind is allowed
        KindValidator::validate($kind);

        // Determine position (append to end)
        $entries = $folder->getEntries();
        $position = count($entries);

        $entry = new FolderEntry($eventId, $kind, $position, $pubkey, $identifier);
        $folder->addEntry($entry);

        // Publish updated folder
        $this->update($folder);

        return $folder;
    }

    /**
     * Remove an entry from a folder
     *
     * @param Folder $folder The folder
     * @param string $eventId The event ID to remove
     * @return Folder The updated folder
     */
    public function removeEntry(Folder $folder, string $eventId): Folder
    {
        $folder->removeEntry($eventId);

        // Reindex positions
        $entries = $folder->getEntries();
        foreach ($entries as $index => $entry) {
            $entry->setPosition($index);
        }

        // Publish updated folder
        $this->update($folder);

        return $folder;
    }

    /**
     * Reorder entries in a folder
     *
     * @param Folder $folder The folder
     * @param array $eventIds Array of event IDs in the desired order
     * @return Folder The updated folder
     * @throws ValidationException If event IDs don't match folder entries
     */
    public function reorderEntries(Folder $folder, array $eventIds): Folder
    {
        $entries = $folder->getEntries();
        $entryMap = [];

        foreach ($entries as $entry) {
            $entryMap[$entry->getEventId()] = $entry;
        }

        // Validate all event IDs are present
        foreach ($eventIds as $eventId) {
            if (!isset($entryMap[$eventId])) {
                throw new ValidationException("Event ID {$eventId} not found in folder");
            }
        }

        // Reorder and update positions
        $reorderedEntries = [];
        foreach ($eventIds as $position => $eventId) {
            $entry = $entryMap[$eventId];
            $entry->setPosition($position);
            $reorderedEntries[] = $entry;
        }

        $folder->setEntries($reorderedEntries);

        // Publish updated folder
        $this->update($folder);

        return $folder;
    }

    /**
     * Convert a Folder domain object to an event array
     *
     * @param Folder $folder
     * @return array
     */
    private function folderToEvent(Folder $folder): array
    {
        $tags = [
            ['d', $folder->getIdentifier()],
            ['name', $folder->getName()],
        ];

        // Add entry tags (both 'e' and 'a' tags for addressable events)
        foreach ($folder->getEntries() as $entry) {
            // Always add 'e' tag with event ID
            $tags[] = [
                'e',
                $entry->getEventId(),
                '',
                (string) $entry->getKind(),
                (string) $entry->getPosition(),
            ];
            
            // Add 'a' tag for addressable replaceable events
            if ($entry->isAddressable()) {
                $tags[] = [
                    'a',
                    $entry->toCoordinate(),
                    '',
                    (string) $entry->getPosition(),
                ];
            }
        }

        foreach ($folder->getTags() as $tag) {
            $tags[] = $tag;
        }

        return [
            'id' => $folder->getId(),
            'kind' => Folder::KIND,
            'pubkey' => $folder->getAddress()->getPubkey(),
            'created_at' => $folder->getCreatedAt(),
            'content' => '',
            'tags' => $tags,
        ];
    }

    /**
     * Convert an event array to a Folder domain object
     *
     * @param array $event
     * @return Folder
     */
    private function eventToFolder(array $event): Folder
    {
        $identifier = '';
        $name = '';
        $tags = [];
        $entries = [];
        $aTagsByPosition = [];

        // First pass: collect 'a' tags by position
        foreach ($event['tags'] ?? [] as $tag) {
            if ($tag[0] === 'a' && isset($tag[1], $tag[3])) {
                $coordinate = $tag[1];
                $position = (int) $tag[3];
                $aTagsByPosition[$position] = $coordinate;
            }
        }

        // Second pass: process all tags
        foreach ($event['tags'] ?? [] as $tag) {
            if ($tag[0] === 'd') {
                $identifier = $tag[1] ?? '';
            } elseif ($tag[0] === 'name') {
                $name = $tag[1] ?? '';
            } elseif ($tag[0] === 'e') {
                // Entry tag: ['e', eventId, relay, kind, position]
                $eventId = $tag[1] ?? '';
                $kind = isset($tag[3]) ? (int) $tag[3] : 0;
                $position = isset($tag[4]) ? (int) $tag[4] : count($entries);

                if (!empty($eventId) && $kind > 0) {
                    // Check if there's a corresponding 'a' tag for this position
                    $pubkey = null;
                    $dtag = null;
                    if (isset($aTagsByPosition[$position])) {
                        $parts = explode(':', $aTagsByPosition[$position], 3);
                        if (count($parts) === 3) {
                            $pubkey = $parts[1];
                            $dtag = $parts[2];
                        }
                    }
                    $entries[] = new FolderEntry($eventId, $kind, $position, $pubkey, $dtag);
                }
            } elseif ($tag[0] !== 'a') {
                // Skip 'a' tags as they're already processed
                $tags[] = $tag;
            }
        }

        // Sort entries by position
        usort($entries, fn(FolderEntry $a, FolderEntry $b) => $a->getPosition() <=> $b->getPosition());

        $address = new Address($event['pubkey'] ?? '', []);
        $folder = new Folder($address, $identifier, $name, $tags);
        $folder->setEntries($entries);

        if (isset($event['id'])) {
            $folder->setId($event['id']);
        }

        if (isset($event['created_at'])) {
            $folder->setCreatedAt($event['created_at']);
            $folder->setUpdatedAt($event['created_at']);
        }

        return $folder;
    }
}
