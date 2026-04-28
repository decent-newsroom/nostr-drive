<?php

declare(strict_types=1);

namespace DecentNewsroom\NostrDrive\Service;

use DecentNewsroom\NostrDrive\Contract\EventStoreInterface;
use DecentNewsroom\NostrDrive\Domain\Coordinate;
use DecentNewsroom\NostrDrive\Domain\Event;
use DecentNewsroom\NostrDrive\Domain\Folder;
use DecentNewsroom\NostrDrive\Domain\FolderEntry;
use DecentNewsroom\NostrDrive\Domain\Meta;
use DecentNewsroom\NostrDrive\Domain\PublishResult;
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
     * Create a new folder and publish it to the event store
     *
     * @param Coordinate   $coordinate The folder's coordinate (must be kind 30045)
     * @param FolderEntry[] $entries   Initial entries
     * @param Meta|null    $meta       Optional title/description metadata
     * @return Event The published event
     * @throws ValidationException If validation fails
     */
    public function create(
        Coordinate $coordinate,
        array $entries = [],
        ?Meta $meta = null
    ): Event {
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

        $folder = new Folder($coordinate, $entries, $meta?->title, $meta?->description);
        return $this->publishFolder($folder);
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
     * Add an entry to a folder and publish the updated event
     *
     * @param Coordinate  $folderCoordinate The folder coordinate
     * @param FolderEntry $entry            The entry to add
     * @return Event The published event
     * @throws ValidationException If kind is not allowed or entry already exists
     */
    public function add(Coordinate $folderCoordinate, FolderEntry $entry): Event
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
        return $this->publishFolder($folder);
    }

    /**
     * Remove an entry from a folder and publish the updated event
     *
     * @param Coordinate $folderCoordinate The folder coordinate
     * @param Coordinate $entryCoordinate  The coordinate of the entry to remove
     * @return Event The published event
     */
    public function remove(Coordinate $folderCoordinate, Coordinate $entryCoordinate): Event
    {
        $folder = $this->get($folderCoordinate);
        $folder->removeEntry($entryCoordinate);
        return $this->publishFolder($folder);
    }

    /**
     * Reorder entries in a folder and publish the updated event
     *
     * @param Coordinate   $folderCoordinate    The folder coordinate
     * @param Coordinate[] $orderedCoordinates  Coordinates in the desired order
     * @return Event The published event
     * @throws ValidationException If coordinates don't match folder entries
     */
    public function reorder(Coordinate $folderCoordinate, array $orderedCoordinates): Event
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
        return $this->publishFolder($folder);
    }

    /**
     * Move an entry from one folder to another
     *
     * @param Coordinate $srcFolderCoordinate  Source folder coordinate
     * @param Coordinate $dstFolderCoordinate  Destination folder coordinate
     * @param Coordinate $entryCoordinate      The coordinate of the entry to move
     * @return array{src: Event, dst: Event} Published events for both folders
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

        // Remove from source, add to destination
        $srcFolder->removeEntry($entryCoordinate);
        $dstFolder->addEntry($entryToMove);

        return [
            'src' => $this->publishFolder($srcFolder),
            'dst' => $this->publishFolder($dstFolder),
        ];
    }

    /**
     * Set entries for a folder (replaces all entries) and publish the updated event
     *
     * @param Coordinate   $folderCoordinate The folder coordinate
     * @param FolderEntry[] $entries         The new entries
     * @return Event The published event
     */
    public function setEntries(Coordinate $folderCoordinate, array $entries): Event
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
        return $this->publishFolder($folder);
    }

    /**
     * Archive a folder (sets status to archived)
     * Note: This does not guarantee network deletion — unlink from parent folder first
     *
     * @param Folder $folder The folder to archive
     * @return PublishResult
     */
    public function archive(Folder $folder): PublishResult
    {
        $event = $this->folderToEvent($folder);
        $archivedEvent = new Event(
            kind: $event->kind,
            pubkey: $event->pubkey,
            createdAt: $event->createdAt,
            tags: array_merge($event->tags, [['status', 'archived']]),
            content: $event->content,
            id: $event->id,
            sig: $event->sig
        );

        return $this->eventStore->publish($archivedEvent);
    }

    /**
     * Publish a Folder domain object to the event store
     */
    private function publishFolder(Folder $folder): Event
    {
        $event = $this->folderToEvent($folder);
        $result = $this->eventStore->publish($event);

        if (!$result->isSuccess()) {
            throw new \RuntimeException('Failed to publish folder event: ' . ($result->message ?? ''));
        }

        return $event;
    }

    /**
     * Convert a Folder domain object to an Event DTO
     * Membership uses `a` tags only (coordinate-first, order = display order)
     * Tag format: ["a", "kind:pubkey:d", "relay-hint", "last-event-id", "name-hint"]
     * Trailing optional fields are omitted when empty
     */
    private function folderToEvent(Folder $folder): Event
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

        // Add membership tags as 'a' tags (order = display order, no explicit position field)
        foreach ($folder->getEntries() as $entry) {
            $aTag = ['a', $entry->getCoordinate()->toString()];

            $relayHint = $entry->getRelayHint() ?? '';
            $lastSeenId = $entry->getLastSeenEventId() ?? '';
            $nameHint = $entry->getNameHint();

            // Only append optional fields when they carry information
            if ($nameHint !== null) {
                // Must include relay-hint and last-event-id positions even if empty
                $aTag[] = $relayHint;
                $aTag[] = $lastSeenId;
                $aTag[] = $nameHint;
            } elseif ($lastSeenId !== '') {
                $aTag[] = $relayHint;
                $aTag[] = $lastSeenId;
            } elseif ($relayHint !== '') {
                $aTag[] = $relayHint;
            }

            $tags[] = $aTag;
        }

        return new Event(
            kind: Folder::KIND,
            pubkey: $coord->getPubkey(),
            createdAt: $folder->getCreatedAt(),
            tags: $tags,
            content: '',
            id: $folder->getEventId()
        );
    }

    /**
     * Convert an Event DTO to a Folder domain object
     */
    private function eventToFolder(Event $event): Folder
    {
        $identifier = '';
        $title = null;
        $description = null;
        $entries = [];

        foreach ($event->tags as $tag) {
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

        $coordinate = new Coordinate(Folder::KIND, $event->pubkey, $identifier);
        $folder = new Folder($coordinate, $entries, $title, $description, $event->toArray());

        if ($event->id !== null) {
            $folder->setEventId($event->id);
        }

        if ($event->createdAt > 0) {
            $folder->setCreatedAt($event->createdAt);
        }

        return $folder;
    }
}
