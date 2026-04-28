<?php
declare(strict_types=1);
namespace DecentNewsroom\NostrDrive\Tests\Domain;
use DecentNewsroom\NostrDrive\Domain\Event;
use PHPUnit\Framework\TestCase;
class EventTest extends TestCase
{
    private const VALID_PUBKEY = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';
    public function testCanCreateEvent(): void
    {
        $event = new Event(
            kind: 30042,
            pubkey: self::VALID_PUBKEY,
            createdAt: 1234567890,
            tags: [['d', 'my-drive']],
            content: ''
        );
        $this->assertSame(30042, $event->kind);
        $this->assertSame(self::VALID_PUBKEY, $event->pubkey);
        $this->assertSame(1234567890, $event->createdAt);
        $this->assertNull($event->id);
        $this->assertNull($event->sig);
    }
    public function testCanCreateEventWithIdAndSig(): void
    {
        $event = new Event(
            kind: 30042,
            pubkey: self::VALID_PUBKEY,
            createdAt: 1234567890,
            tags: [],
            content: '',
            id: 'abc123',
            sig: 'sig456'
        );
        $this->assertSame('abc123', $event->id);
        $this->assertSame('sig456', $event->sig);
    }
    public function testFromArrayRoundTrip(): void
    {
        $rawEvent = [
            'id' => 'event123',
            'kind' => 30045,
            'pubkey' => self::VALID_PUBKEY,
            'created_at' => 1234567890,
            'content' => '',
            'sig' => 'sig789',
            'tags' => [
                ['d', 'my-folder'],
                ['title', 'My Folder'],
                ['a', '30040:' . self::VALID_PUBKEY . ':file1'],
            ],
        ];
        $event = Event::fromArray($rawEvent);
        $this->assertSame('event123', $event->id);
        $this->assertSame(30045, $event->kind);
        $this->assertSame(self::VALID_PUBKEY, $event->pubkey);
        $this->assertSame(1234567890, $event->createdAt);
        $this->assertSame('sig789', $event->sig);
        $this->assertCount(3, $event->tags);
    }
    public function testGetTagValueReturnsFirstMatch(): void
    {
        $event = new Event(
            kind: 30045,
            pubkey: self::VALID_PUBKEY,
            createdAt: 1000,
            tags: [
                ['d', 'my-folder'],
                ['title', 'My Folder'],
            ]
        );
        $this->assertSame('my-folder', $event->getTagValue('d'));
        $this->assertSame('My Folder', $event->getTagValue('title'));
        $this->assertNull($event->getTagValue('description'));
    }
    public function testGetTagValuesReturnsAllMatches(): void
    {
        $a1 = ['a', '30045:' . self::VALID_PUBKEY . ':themes'];
        $a2 = ['a', '30045:' . self::VALID_PUBKEY . ':magazines'];
        $event = new Event(
            kind: 30042,
            pubkey: self::VALID_PUBKEY,
            createdAt: 1000,
            tags: [
                ['d', 'drive'],
                $a1,
                $a2,
            ]
        );
        $aTags = $event->getTagValues('a');
        $this->assertCount(2, $aTags);
        $this->assertSame($a1, $aTags[0]);
        $this->assertSame($a2, $aTags[1]);
    }
    public function testToArrayOmitsNullIdAndSig(): void
    {
        $event = new Event(
            kind: 30042,
            pubkey: self::VALID_PUBKEY,
            createdAt: 1000,
            tags: []
        );
        $arr = $event->toArray();
        $this->assertArrayNotHasKey('id', $arr);
        $this->assertArrayNotHasKey('sig', $arr);
    }
    public function testToArrayIncludesIdAndSigWhenPresent(): void
    {
        $event = new Event(
            kind: 30042,
            pubkey: self::VALID_PUBKEY,
            createdAt: 1000,
            tags: [],
            content: '',
            id: 'myid',
            sig: 'mysig'
        );
        $arr = $event->toArray();
        $this->assertSame('myid', $arr['id']);
        $this->assertSame('mysig', $arr['sig']);
    }
}