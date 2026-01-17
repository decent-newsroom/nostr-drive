<?php

declare(strict_types=1);

namespace DecentNewsroom\NostrDrive\Service;

use DecentNewsroom\NostrDrive\Contract\EventStoreInterface;
use DecentNewsroom\NostrDrive\Domain\Coordinate;
use DecentNewsroom\NostrDrive\Domain\Folder;
use DecentNewsroom\NostrDrive\Domain\FolderEntry;
use DecentNewsroom\NostrDrive\Exception\NotFoundException;
use DecentNewsroom\NostrDrive\Exception\ValidationException;
use DecentNewsroom\NostrDrive\Validation\KindValidator;

/**
 * Service for managing Folder entities (kind:30045)
 * Provides CRUD operations plus coordinate-based membership management (add/remove/reorder)
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
     * @param Coordinate $coordinate The folder's coordinate (must be kind 30045)
     * @param FolderEntry[] $entries Initial entries
     * @param string|null $title The folder title
     * @param string|null $description The folder description
     * @return Folder The created folder
     * @throws ValidationException If validation fails
     */
    public function create(
        Coordinate $coordinate,
        array $entries = [],
        ?string $title = null,
        ?string $description = null
    ): Folder {
        if ($coordinate->getKind() !== Folder::KIND) {
            throw new ValidationException(
                'Folder coordinate must be kind ' . Folder::KIND . ', got ' . $coordinate->getKind()
            );
        }

        // Validate entry kinds
        foreach ($entries as $entry) {
            if (!$entry instanceof FolderEntry) {
                throw new ValidationException('All entries must be FolderEntry instances');
            }
            KindValidator::validate($entry->getCoordinate()->getKind());
        }

        $folder = new Folder($coordinate, $entries, $title, $description);

        // Publish to event store
        $event = $this->folderToEvent($folder);
        $this->eventStore->publish($event);

        return $folder;
    }

    /**
     * Get a folder by coordinate
     *
     * @param Coordinate $coordinate The folder coordinate
     * @return Folder The folder
     * @throws NotFoundException If folder not found
     */
    public function get(Coordinate $coordinate): Folder
    {
        if ($coordinate->getKind() !== Folder::KIND) {
            throw new ValidationException(
                'Coordinate must be kind ' . Folder::KIND . ', got ' . $coordinate->getKind()
            );
        }

        $event = $this->eventStore->getLatestByCoordinate($coordinate);

        if ($event === null) {
            throw new NotFoundException("Folder not found for coordinate: {$coordinate}");
        }

        return $this->eventToFolder($event);
    }

    /**
     * Update an existing folder (replaces the event with same coordinate)
     *
     * @param Folder $folder The folder to update
     * @return Folder The updated folder
     */
    public function update(Folder $folder): Folder
    {
        $event = $this->folderToEvent($folder);
        $this->eventStore->publish($event);

        return $folder;
    }

    /**
     * Add an entry to a folder
     *
     * @param Coordinate $folderCoordinate The folder coordinate
     * @param FolderEntry $entry The entry to add
     * @return Folder The updated folder
     * @throws ValidationException If kind is not allowed or entry already exists
     */
    public function addEntry(Coordinate $folderCoordinate, FolderEntry $entry): Folder
    {
        // Validate the kind is allowed
        KindValidator::validate($entry->getCoordinate()->getKind());

        $folder = $this->get($folderCoordinate);

        // Check if entry already exists
        if ($folder->hasEntry($entry->getCoordinate())) {
            throw new ValidationException(
                "Entry with coordinate {$entry->getCoordinate()} already exists in folder"
            );
        }

        $folder->addEntry($entry);

        // Publish updated folder
        return $this->update($folder);
    }

    /**
     * Remove an entry from a folder
     *
     * @param Coordinate $folderCoordinate The folder coordinate
     * @param Coordinate $entryCoordinate The coordinate of the entry to remove
     * @return Folder The updated folder
     */
    public function removeEntry(Coordinate $folderCoordinate, Coordinate $entryCoordinate): Folder
    {
        $folder = $this->get($folderCoordinate);
        $folder->removeEntry($entryCoordinate);

        // Publish updated folder
        return $this->update($folder);
    }

    /**
     * Move an entry from one folder to another
     *
     * @param Coordinate $srcFolderCoordinate Source folder coordinate
     * @param Coordinate $dstFolderCoordinate Destination folder coordinate
     * @param Coordinate $entryCoordinate The coordinate of the entry to move
     * @return array{src: Folder, dst: Folder} Both updated folders
     */
    public function moveEntry(
        Coordinate $srcFolderCoordinate,
        Coordinate $dstFolderCoordinate,
        Coordinate $entryCoordinate
    ): array {
        $srcFolder = $this->get($srcFolderCoordinate);
        $dstFolder = $this->get($dstFolderCoordinate);

        // Find entry in source
        $entryToMove = null;
        foreach ($srcFolder->getEntries() as $entry) {
            if ($entry->getCoordinate()->equals($entryCoordinate)) {
                $entryToMove = $entry;
                break;
            }
        }

        if ($entryToMove === null) {
            throw new NotFoundException(
                "Entry with coordinate {$entryCoordinate} not found in source folder"
            );
        }

        // Remove from source
        $srcFolder->removeEntry($entryCoordinate);

        // Add to destination
        $dstFolder->addEntry($entryToMove);

        // Publish both folders
        $srcFolder = $this->update($srcFolder);
        $dstFolder = $this->update($dstFolder);

        return ['src' => $srcFolder, 'dst' => $dstFolder];
    }

    /**
     * Reorder entries in a folder
     *
     * @param Coordinate $folderCoordinate The folder coordinate
     * @param Coordinate[] $orderedCoordinates Array of coordinates in the desired order
     * @return Folder The updated folder
     * @throws ValidationException If coordinates don't match folder entries
     */
    public function reorderEntries(Coordinate $folderCoordinate, array $orderedCoordinates): Folder
    {
        $folder = $this->get($folderCoordinate);
        $entries = $folder->getEntries();
        $entryMap = [];

        // Build map of existing entries
        foreach ($entries as $entry) {
            $entryMap[$entry->getCoordinate()->toString()] = $entry;
        }

        // Validate all coordinates are present
        foreach ($orderedCoordinates as $coord) {
            if (!$coord instanceof Coordinate) {
                throw new ValidationException('All items must be Coordinate instances');
            }
            if (!isset($entryMap[$coord->toString()])) {
                throw new ValidationException("Coordinate {$coord} not found in folder");
            }
        }

        // Validate all existing entries are in the new order
        if (count($orderedCoordinates) !== count($entries)) {
            throw new ValidationException(
                'Reorder must include all existing entries. Expected ' .
                count($entries) . ', got ' . count($orderedCoordinates)
            );
        }

        // Reorder
        $reorderedEntries = [];
        foreach ($orderedCoordinates as $coord) {
            $reorderedEntries[] = $entryMap[$coord->toString()];
        }

        $folder->setEntries($reorderedEntries);

        // Publish updated folder
        return $this->update($folder);
    }

    /**
     * Set entries for a folder (replaces all entries)
     *
     * @param Coordinate $folderCoordinate The folder coordinate
     * @param FolderEntry[] $entries The new entries
     * @return Folder The updated folder
     */
    public function setEntries(Coordinate $folderCoordinate, array $entries): Folder
    {
        // Validate entry kinds
        foreach ($entries as $entry) {
            if (!$entry instanceof FolderEntry) {
                throw new ValidationException('All entries must be FolderEntry instances');
            }
            KindValidator::validate($entry->getCoordinate()->getKind());
        }

        $folder = $this->get($folderCoordinate);
        $folder->setEntries($entries);

        // Publish updated folder
        return $this->update($folder);
    }

    /**
     * Archive a folder (sets status to archived)
     * Note: This does not guarantee network deletion
     *
     * @param Folder $folder The folder to archive
     * @return bool True if successful
     */
    public function archive(Folder $folder): bool
    {
        $event = $this->folderToEvent($folder);
        $event['tags'][] = ['status', 'archived'];

        return $this->eventStore->publish($event);
    }

    /**
     * Convert a Folder domain object to an event array
     *
     * @param Folder $folder
     * @return array
     */
    private function folderToEvent(Folder $folder): array
    {
        $coord = $folder->getCoordinate();

        $tags = [
            ['d', $coord->getIdentifier()],
        ];

        if ($folder->getTitle() !== null) {
            $tags[] = ['title', $folder->getTitle()];
        }

        if ($folder->getDescription() !== null) {
            $tags[] = ['description', $folder->getDescription()];
        }

        // Add membership tags as 'a' tags (order matters)
        foreach ($folder->getEntries() as $entry) {
            $aTag = ['a', $entry->getCoordinate()->toString()];

            if ($entry->getRelayHint() !== null) {
                $aTag[] = $entry->getRelayHint();
            } else {
                $aTag[] = '';
            }

            // Optional: add last seen event ID as hint
            if ($entry->getLastSeenEventId() !== null) {
                $aTag[] = $entry->getLastSeenEventId();
            } else {
                $aTag[] = '';
            }

            // Optional: add name hint
            if ($entry->getNameHint() !== null) {
                $aTag[] = $entry->getNameHint();
            }

            $tags[] = $aTag;
        }

        return [
            'id' => $folder->getEventId(),
            'kind' => Folder::KIND,
            'pubkey' => $coord->getPubkey(),
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
        $title = null;
        $description = null;
        $entries = [];

        foreach ($event['tags'] ?? [] as $tag) {
            if ($tag[0] === 'd') {
                $identifier = $tag[1] ?? '';
            } elseif ($tag[0] === 'title') {
                $title = $tag[1] ?? null;
            } elseif ($tag[0] === 'description') {
                $description = $tag[1] ?? null;
            } elseif ($tag[0] === 'a') {
                // Parse membership coordinate
                $coordinateStr = $tag[1] ?? '';
                if (empty($coordinateStr)) {
                    continue;
                }

                try {
                    $entryCoord = Coordinate::parse($coordinateStr);

                    // Extract optional hints
                    $relayHint = isset($tag[2]) && $tag[2] !== '' ? $tag[2] : null;
                    $lastSeenEventId = isset($tag[3]) && $tag[3] !== '' ? $tag[3] : null;
                    $nameHint = isset($tag[4]) && $tag[4] !== '' ? $tag[4] : null;

                    $entries[] = new FolderEntry(
                        $entryCoord,
                        $relayHint,
                        $lastSeenEventId,
                        $nameHint
                    );
                } catch (\InvalidArgumentException $e) {
                    // Skip invalid coordinates
                }
            }
        }

        $pubkey = $event['pubkey'] ?? '';
        $coordinate = new Coordinate(Folder::KIND, $pubkey, $identifier);

        $folder = new Folder($coordinate, $entries, $title, $description, $event);

        if (isset($event['id'])) {
            $folder->setEventId($event['id']);
        }

        if (isset($event['created_at'])) {
            $folder->setCreatedAt($event['created_at']);
        }

        return $folder;
    }
}
