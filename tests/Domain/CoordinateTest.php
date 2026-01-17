<?php

declare(strict_types=1);

namespace DecentNewsroom\NostrDrive\Tests\Domain;

use DecentNewsroom\NostrDrive\Domain\Coordinate;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CoordinateTest extends TestCase
{
    private const VALID_PUBKEY = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    public function testCanCreateCoordinate(): void
    {
        $coord = new Coordinate(30042, self::VALID_PUBKEY, 'my-drive');

        $this->assertSame(30042, $coord->getKind());
        $this->assertSame(self::VALID_PUBKEY, $coord->getPubkey());
        $this->assertSame('my-drive', $coord->getIdentifier());
    }

    public function testCanCreateCoordinateWithEmptyIdentifier(): void
    {
        $coord = new Coordinate(30042, self::VALID_PUBKEY, '');

        $this->assertSame('', $coord->getIdentifier());
    }

    public function testToStringReturnsCoordinateFormat(): void
    {
        $coord = new Coordinate(30042, self::VALID_PUBKEY, 'my-drive');
        $expected = '30042:' . self::VALID_PUBKEY . ':my-drive';

        $this->assertSame($expected, $coord->toString());
        $this->assertSame($expected, (string) $coord);
    }

    public function testParseValidCoordinate(): void
    {
        $coordinateStr = '30045:' . self::VALID_PUBKEY . ':themes';
        $coord = Coordinate::parse($coordinateStr);

        $this->assertSame(30045, $coord->getKind());
        $this->assertSame(self::VALID_PUBKEY, $coord->getPubkey());
        $this->assertSame('themes', $coord->getIdentifier());
    }

    public function testParseWithEmptyIdentifier(): void
    {
        $coordinateStr = '30042:' . self::VALID_PUBKEY . ':';
        $coord = Coordinate::parse($coordinateStr);

        $this->assertSame('', $coord->getIdentifier());
    }

    public function testParseInvalidFormatThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid coordinate format');

        Coordinate::parse('invalid:format');
    }

    public function testParseNonNumericKindThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Kind must be numeric');

        Coordinate::parse('abc:' . self::VALID_PUBKEY . ':test');
    }

    public function testNonAddressableKindThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not addressable');

        new Coordinate(1, self::VALID_PUBKEY, 'test');
    }

    public function testKindTooLowThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not addressable');

        new Coordinate(29999, self::VALID_PUBKEY, 'test');
    }

    public function testKindTooHighThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not addressable');

        new Coordinate(40000, self::VALID_PUBKEY, 'test');
    }

    public function testEmptyPubkeyThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pubkey cannot be empty');

        new Coordinate(30042, '', 'test');
    }

    public function testInvalidPubkeyFormatThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('valid 64-character hex string');

        new Coordinate(30042, 'invalid', 'test');
    }

    public function testEqualsSameCoordinate(): void
    {
        $coord1 = new Coordinate(30042, self::VALID_PUBKEY, 'my-drive');
        $coord2 = new Coordinate(30042, self::VALID_PUBKEY, 'my-drive');

        $this->assertTrue($coord1->equals($coord2));
        $this->assertTrue($coord2->equals($coord1));
    }

    public function testEqualsDifferentKind(): void
    {
        $coord1 = new Coordinate(30042, self::VALID_PUBKEY, 'test');
        $coord2 = new Coordinate(30045, self::VALID_PUBKEY, 'test');

        $this->assertFalse($coord1->equals($coord2));
    }

    public function testEqualsDifferentPubkey(): void
    {
        $pubkey2 = 'fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210';
        $coord1 = new Coordinate(30042, self::VALID_PUBKEY, 'test');
        $coord2 = new Coordinate(30042, $pubkey2, 'test');

        $this->assertFalse($coord1->equals($coord2));
    }

    public function testEqualsDifferentIdentifier(): void
    {
        $coord1 = new Coordinate(30042, self::VALID_PUBKEY, 'test1');
        $coord2 = new Coordinate(30042, self::VALID_PUBKEY, 'test2');

        $this->assertFalse($coord1->equals($coord2));
    }

    public function testRoundtripParseToString(): void
    {
        $original = '30045:' . self::VALID_PUBKEY . ':themes/default';
        $coord = Coordinate::parse($original);
        $result = $coord->toString();

        $this->assertSame($original, $result);
    }
}
