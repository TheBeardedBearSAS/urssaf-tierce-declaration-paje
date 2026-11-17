<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Shared;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\PostalCode;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

final class PostalCodeTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function provideValidValues(): iterable
    {
        yield 'paris' => ['75000'];
        yield 'leading zero' => ['01000'];
        yield 'corsica' => ['2A000'];
        yield 'five letters' => ['ABCDE'];
        yield 'five accented characters' => ['ééééé'];
    }

    #[DataProvider('provideValidValues')]
    public function testFromStringKeepsTheValue(string $value): void
    {
        static::assertSame($value, PostalCode::fromString($value)->value());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalidValues(): iterable
    {
        yield 'empty' => ['', 'postal_code.too_short'];
        yield 'four characters' => ['7500', 'postal_code.too_short'];
        yield 'four accented characters' => ['éééé', 'postal_code.too_short'];
        yield 'six characters' => ['750000', 'postal_code.too_long'];
        yield 'six accented characters' => ['éééééé', 'postal_code.too_long'];
    }

    #[DataProvider('provideInvalidValues')]
    public function testFromStringRejectsAWrongLength(string $value, string $code): void
    {
        static::assertSame($code, DomainAssertions::assertViolationCode($code, static fn(): PostalCode => PostalCode::fromString($value))->violationCode());
    }

    public function testSubjectIsConfigurable(): void
    {
        $tooShort = DomainAssertions::assertViolationCode('employer_postal_code.too_short', static fn(): PostalCode => PostalCode::fromString('1', 'employer_postal_code'));
        $tooLong = DomainAssertions::assertViolationCode('employee_postal_code.too_long', static fn(): PostalCode => PostalCode::fromString('123456', 'employee_postal_code'));

        static::assertSame(['min_length' => 5], $tooShort->params());
        static::assertSame(['max_length' => 5], $tooLong->params());
    }

    public function testMessagesNeverEchoTheValue(): void
    {
        $exception = DomainAssertions::assertViolationCode('postal_code.too_long', static fn(): PostalCode => PostalCode::fromString('123456'));

        static::assertStringNotContainsString('123456', $exception->getMessage());
    }

    public function testEquality(): void
    {
        static::assertTrue(PostalCode::fromString('75000')->equals(PostalCode::fromString('75000')));
        static::assertFalse(PostalCode::fromString('75000')->equals(PostalCode::fromString('75001')));
    }
}
