<?php

declare(strict_types=1);

namespace DecentNewsroom\NostrDrive\Service;

use DecentNewsroom\NostrDrive\Contract\EventStoreInterface;
use DecentNewsroom\NostrDrive\Domain\Coordinate;
use DecentNewsroom\NostrDrive\Domain\Drive;
use DecentNewsroom\NostrDrive\Domain\Event;
use DecentNewsroom\NostrDrive\Domain\Folder;
use DecentNewsroom\NostrDrive\Domain\Meta;
use DecentNewsroom\NostrDrive\Domain\PublishResult;
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
     * Create a new drive and publish it to the event store
     *
     * @param Coordinate    $coordinate The drive's coordinate (must be kind 30042)
     * @param Coordinate[]  $roots      Array of root folder coordinates (kind 30045)
     * @param Meta|null     $meta       Optional title/description metadata
     * @return Event The published event
     * @throws ValidationException If validation fails
     */
    public function create(
        Coordinate $coordinate,
        array $roots = [],
        ?Meta $meta = null
    ): Event {
        if ($coordinate->getKind() !== Drive::KIND) {
            throw new ValidationException(
                'Drive coordinate must be kind ' . Drive::KIND . ', got ' . $coordinate->getKind()
            );
        }

        // Validate roots are all kind 30045
        foreach ($roots as $root) {
            if (!$root instanceof Coordinate) {
                throw new ValidationException('All roots must be Coordinate instances');
            }
            if ($root->getKind() !== Folder::KIND) {
                throw new ValidationException(
                    'Root folder coordinates must be kind ' . Folder::KIND . ', got ' . $root->getKind()
                );
            }
        }

        $drive = new Drive($coordinate, $roots, $meta?->title, $meta?->description);
        return $this->publishDrive($drive);
    }

    /**
     * Get a drive by coordinate
     *
     * @param Coordinate $coordinate The drive coordinate
     * @return Drive The drive
     * @throws NotFoundException If drive not found
     */
    public function get(Coordinate $coordinate): Drive
    {
        if ($coordinate->getKind() !== Drive::KIND) {
            throw new ValidationException(
                'Coordinate must be kind ' . Drive::KIND . ', got ' . $coordinate->getKind()
            );
        }

        $event = $this->eventStore->getLatestByCoordinate($coordinate);

        if ($event === null) {
            throw new NotFoundException("Drive not found for coordinate: {$coordinate}");
        }

        return $this->eventToDrive($event);
    }

    /**
     * Set root folders for a drive and publish the updated event
     *
     * @param Coordinate   $coordinate The drive coordinate
     * @param Coordinate[] $roots      Array of root folder coordinates
     * @return Event The published event
     */
    public function setRoots(Coordinate $coordinate, array $roots): Event
    {
        $drive = $this->get($coordinate);
        $drive->setRoots($roots);
        return $this->publishDrive($drive);
    }

    /**
     * Archive a drive (sets status to archived)
     * Note: This does not guarantee network deletion
     *
     * @param Drive $drive The drive to archive
     * @return PublishResult
     */
    public function archive(Drive $drive): PublishResult
    {
        $event = $this->driveToEvent($drive);
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
     * Publish a Drive domain object to the event store
     */
    private function publishDrive(Drive $drive): Event
    {
        $event = $this->driveToEvent($drive);
        $result = $this->eventStore->publish($event);

        if (!$result->isSuccess()) {
            throw new \RuntimeException('Failed to publish drive event: ' . ($result->message ?? ''));
        }

        return $event;
    }

    /**
     * Convert a Drive domain object to an Event DTO
     */
    private function driveToEvent(Drive $drive): Event
    {
        $coord = $drive->getCoordinate();

        $tags = [
            ['d', $coord->getIdentifier()],
        ];

        if ($drive->getTitle() !== null) {
            $tags[] = ['title', $drive->getTitle()];
        }

        if ($drive->getDescription() !== null) {
            $tags[] = ['description', $drive->getDescription()];
        }

        // Add root folder mounts as 'a' tags (order matters)
        foreach ($drive->getRoots() as $root) {
            $tags[] = ['a', $root->toString()];
        }

        return new Event(
            kind: Drive::KIND,
            pubkey: $coord->getPubkey(),
            createdAt: $drive->getCreatedAt(),
            tags: $tags,
            content: '',
            id: $drive->getEventId()
        );
    }

    /**
     * Convert an Event DTO to a Drive domain object
     */
    private function eventToDrive(Event $event): Drive
    {
        $identifier = '';
        $title = null;
        $description = null;
        $roots = [];

        foreach ($event->tags as $tag) {
            if ($tag[0] === 'd') {
                $identifier = $tag[1] ?? '';
            } elseif ($tag[0] === 'title') {
                $title = $tag[1] ?? null;
            } elseif ($tag[0] === 'description') {
                $description = $tag[1] ?? null;
            } elseif ($tag[0] === 'a') {
                // Parse root folder coordinate
                try {
                    $rootCoord = Coordinate::parse($tag[1]);
                    if ($rootCoord->getKind() === Folder::KIND) {
                        $roots[] = $rootCoord;
                    }
                } catch (\InvalidArgumentException $e) {
                    // Skip invalid coordinates
                }
            }
        }

        $coordinate = new Coordinate(Drive::KIND, $event->pubkey, $identifier);
        $drive = new Drive($coordinate, $roots, $title, $description, $event->toArray());

        if ($event->id !== null) {
            $drive->setEventId($event->id);
        }

        if ($event->createdAt > 0) {
            $drive->setCreatedAt($event->createdAt);
        }

        return $drive;
    }
}
