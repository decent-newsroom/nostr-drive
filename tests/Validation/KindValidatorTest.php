<?php

declare(strict_types=1);

namespace DecentNewsroom\NostrDrive\Tests\Validation;

use DecentNewsroom\NostrDrive\Exception\InvalidKindException;
use DecentNewsroom\NostrDrive\Validation\KindValidator;
use PHPUnit\Framework\TestCase;

class KindValidatorTest extends TestCase
{
    public function testAllowedKindsAreValid(): void
    {
        $allowedKinds = [30040, 30041, 30024, 30023, 31924, 31923, 31922];

        foreach ($allowedKinds as $kind) {
            $this->assertTrue(KindValidator::isAllowed($kind));
            KindValidator::validate($kind); // Should not throw
        }

        $this->assertTrue(true); // Assert test completed without exceptions
    }

    public function testDisallowedKindsThrowException(): void
    {
        $this->expectException(InvalidKindException::class);

        KindValidator::validate(1); // Kind 1 is not allowed
    }

    public function testIsAllowedReturnsFalseForDisallowedKinds(): void
    {
        $this->assertFalse(KindValidator::isAllowed(1));
        $this->assertFalse(KindValidator::isAllowed(999));
        $this->assertFalse(KindValidator::isAllowed(30042));
    }

    public function testGetAllowedKindsReturnsArray(): void
    {
        $allowedKinds = KindValidator::getAllowedKinds();

        $this->assertIsArray($allowedKinds);
        $this->assertContains(30040, $allowedKinds);
        $this->assertContains(30041, $allowedKinds);
        $this->assertContains(30024, $allowedKinds);
        $this->assertContains(30023, $allowedKinds);
        $this->assertContains(31924, $allowedKinds);
        $this->assertContains(31923, $allowedKinds);
        $this->assertContains(31922, $allowedKinds);
    }
}
