<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Shared;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

final class StringIdentifiersTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string<TenantId|ExternalReference>, string, positive-int}>
     */
    public static function provideFactories(): iterable
    {
        yield 'tenant id' => [TenantId::class, 'tenant_id', 64];
        yield 'external reference' => [ExternalReference::class, 'external_reference', 191];
    }

    /**
     * @param class-string<TenantId|ExternalReference> $class
     * @param positive-int                             $maxLength
     */
    #[DataProvider('provideFactories')]
    public function testValuesAreKeptVerbatim(string $class, string $subject, int $maxLength): void
    {
        foreach (['a', ' padded value ', str_repeat('x', $maxLength), str_repeat('é', $maxLength)] as $value) {
            $identifier = $class::fromString($value);
            static::assertSame($value, $identifier->value());
            static::assertSame($value, (string) $identifier);
        }
        static::assertSame($class::MAX_LENGTH, $maxLength);
    }

    /**
     * @param class-string<TenantId|ExternalReference> $class
     * @param positive-int                             $maxLength
     */
    #[DataProvider('provideFactories')]
    public function testBlankValuesAreRejected(string $class, string $subject, int $maxLength): void
    {
        foreach (['', ' ', "\t\n"] as $value) {
            try {
                $class::fromString($value);
                static::fail('Expected exception');
            } catch (InvalidValue $exception) {
                static::assertSame($subject . '.empty', $exception->violationCode());
            }
        }
    }

    /**
     * @param class-string<TenantId|ExternalReference> $class
     * @param positive-int                             $maxLength
     */
    #[DataProvider('provideFactories')]
    public function testTooLongValuesAreRejected(string $class, string $subject, int $maxLength): void
    {
        try {
            $class::fromString(str_repeat('x', $maxLength + 1));
            static::fail('Expected exception');
        } catch (InvalidValue $exception) {
            static::assertSame($subject . '.too_long', $exception->violationCode());
            static::assertSame(['max_length' => $maxLength], $exception->params());
        }
    }

    public function testEquality(): void
    {
        static::assertTrue(TenantId::fromString('acme')->equals(TenantId::fromString('acme')));
        static::assertFalse(TenantId::fromString('acme')->equals(TenantId::fromString('Acme')));
        static::assertTrue(ExternalReference::fromString('r1')->equals(ExternalReference::fromString('r1')));
        static::assertFalse(ExternalReference::fromString('r1')->equals(ExternalReference::fromString('r2')));
    }
}
