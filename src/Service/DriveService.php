<?php

declare(strict_types=1);

namespace DecentNewsroom\NostrDrive\Service;

use DecentNewsroom\NostrDrive\Contract\EventStoreInterface;
use DecentNewsroom\NostrDrive\Domain\Coordinate;
use DecentNewsroom\NostrDrive\Domain\Drive;
use DecentNewsroom\NostrDrive\Domain\Folder;
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
     * @param Coordinate $coordinate The drive's coordinate (must be kind 30042)
     * @param Coordinate[] $roots Array of root folder coordinates (kind 30045)
     * @param string|null $title The drive title
     * @param string|null $description The drive description
     * @return Drive The created drive
     * @throws ValidationException If validation fails
     */
    public function create(
        Coordinate $coordinate,
        array $roots = [],
        ?string $title = null,
        ?string $description = null
    ): Drive {
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

        $drive = new Drive($coordinate, $roots, $title, $description);

        // Publish to event store
        $event = $this->driveToEvent($drive);
        $this->eventStore->publish($event);

        return $drive;
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
     * Update an existing drive (replaces the event with same coordinate)
     *
     * @param Drive $drive The drive to update
     * @return Drive The updated drive
     */
    public function update(Drive $drive): Drive
    {
        $event = $this->driveToEvent($drive);
        $this->eventStore->publish($event);

        return $drive;
    }

    /**
     * Set root folders for a drive
     *
     * @param Coordinate $coordinate The drive coordinate
     * @param Coordinate[] $roots Array of root folder coordinates
     * @return Drive The updated drive
     */
    public function setRoots(Coordinate $coordinate, array $roots): Drive
    {
        $drive = $this->get($coordinate);
        $drive->setRoots($roots);
        return $this->update($drive);
    }

    /**
     * Archive a drive (sets status to archived)
     * Note: This does not guarantee network deletion
     *
     * @param Drive $drive The drive to archive
     * @return bool True if successful
     */
    public function archive(Drive $drive): bool
    {
        $event = $this->driveToEvent($drive);
        $event['tags'][] = ['status', 'archived'];

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

        // Add root folder mounts as 'a' tags
        foreach ($drive->getRoots() as $root) {
            $tags[] = ['a', $root->toString()];
        }

        return [
            'id' => $drive->getEventId(),
            'kind' => Drive::KIND,
            'pubkey' => $coord->getPubkey(),
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
        $title = null;
        $description = null;
        $roots = [];

        foreach ($event['tags'] ?? [] as $tag) {
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

        $pubkey = $event['pubkey'] ?? '';
        $coordinate = new Coordinate(Drive::KIND, $pubkey, $identifier);

        $drive = new Drive($coordinate, $roots, $title, $description, $event);

        if (isset($event['id'])) {
            $drive->setEventId($event['id']);
        }

        if (isset($event['created_at'])) {
            $drive->setCreatedAt($event['created_at']);
        }

        return $drive;
    }
}
