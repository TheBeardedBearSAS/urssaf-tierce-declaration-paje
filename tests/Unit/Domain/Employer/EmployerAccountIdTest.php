<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Employer;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;

final class EmployerAccountIdTest extends TestCase
{
    public function testValidValues(): void
    {
        static::assertSame(' a ', EmployerAccountId::fromString(' a ')->value());
        static::assertSame('id-1', (string) EmployerAccountId::fromString('id-1'));
        static::assertSame(str_repeat('é', 64), EmployerAccountId::fromString(str_repeat('é', 64))->value());
    }

    public function testBlankValues(): void
    {
        $this->expectExceptionObject(InvalidValue::empty('employer_account_id'));

        EmployerAccountId::fromString("  \n");
    }

    public function testTooLong(): void
    {
        $this->expectExceptionObject(InvalidValue::tooLong('employer_account_id', 64));

        EmployerAccountId::fromString(str_repeat('é', 65));
    }

    public function testEquality(): void
    {
        static::assertTrue(EmployerAccountId::fromString('a')->equals(EmployerAccountId::fromString('a')));
        static::assertFalse(EmployerAccountId::fromString('a')->equals(EmployerAccountId::fromString('b')));
    }
}
