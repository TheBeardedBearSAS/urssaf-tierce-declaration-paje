<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Mandate;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateId;

final class MandateIdTest extends TestCase
{
    public function testValidValues(): void
    {
        static::assertSame(' a ', MandateId::fromString(' a ')->value());
        static::assertSame('id-1', (string) MandateId::fromString('id-1'));
        static::assertSame(str_repeat('é', 64), MandateId::fromString(str_repeat('é', 64))->value());
    }

    public function testBlankValues(): void
    {
        $this->expectExceptionObject(InvalidValue::empty('mandate_id'));

        MandateId::fromString("  \n");
    }

    public function testTooLong(): void
    {
        $this->expectExceptionObject(InvalidValue::tooLong('mandate_id', 64));

        MandateId::fromString(str_repeat('é', 65));
    }

    public function testEquality(): void
    {
        static::assertTrue(MandateId::fromString('a')->equals(MandateId::fromString('a')));
        static::assertFalse(MandateId::fromString('a')->equals(MandateId::fromString('b')));
    }
}
