<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Validation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\Severity;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\ViolationCollector;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

final class ViolationCollectorTypedFieldsTest extends TestCase
{
    /**
     * @return iterable<string, array{?int, list<string>, ?int}>
     */
    public static function provideIntegers(): iterable
    {
        yield 'below min' => [0, ['error streetNumber street_number.out_of_range'], null];
        yield 'min' => [1, [], 1];
        yield 'inside' => [500, [], 500];
        yield 'max' => [9999, [], 9999];
        yield 'above max' => [10_000, ['error streetNumber street_number.out_of_range'], null];
    }

    /**
     * @param list<string> $summary
     */
    #[DataProvider('provideIntegers')]
    public function testRequiredInteger(?int $raw, array $summary, ?int $expected): void
    {
        $collector = new ViolationCollector();

        static::assertSame($expected, $collector->requiredInteger('streetNumber', 'street_number', $raw, 1, 9999));
        static::assertSame($summary, DomainAssertions::summary($collector->result()));
    }

    /**
     * @param list<string> $summary
     */
    #[DataProvider('provideIntegers')]
    public function testOptionalInteger(?int $raw, array $summary, ?int $expected): void
    {
        $collector = new ViolationCollector();

        static::assertSame($expected, $collector->optionalInteger('streetNumber', 'street_number', $raw, 1, 9999));
        static::assertSame($summary, DomainAssertions::summary($collector->result()));
    }

    public function testOutOfRangeViolationDetails(): void
    {
        $collector = new ViolationCollector();
        $collector->requiredInteger('address.streetNumber', 'street_number', 0, 1, 9999);

        $violation = $collector->result()->all()[0];
        static::assertSame('address.streetNumber', $violation->path());
        static::assertSame('Invalid street_number: the value must be between 1 and 9999.', $violation->message());
        static::assertSame(['min' => 1, 'max' => 9999], $violation->params());
        static::assertSame(Severity::Error, $violation->severity());
    }

    public function testMissingIntegers(): void
    {
        $collector = new ViolationCollector();

        static::assertNull($collector->optionalInteger('streetNumber', 'street_number', null, 1, 9999));
        static::assertSame(0, $collector->errorCount());
        static::assertNull($collector->requiredInteger('streetNumber', 'street_number', null, 1, 9999));

        $violation = $collector->result()->all()[0];
        static::assertSame('street_number.required', $violation->code());
        static::assertSame('The street number is required.', $violation->message());
        static::assertSame(1, $collector->errorCount());
    }

    public function testRequiredBool(): void
    {
        $collector = new ViolationCollector();

        static::assertTrue($collector->requiredBool('sepaMandate', 'sepa_mandate', true));
        static::assertFalse($collector->requiredBool('sepaMandate', 'sepa_mandate', false));
        static::assertSame(0, $collector->errorCount());
        static::assertNull($collector->requiredBool('bankDetails.sepaMandate', 'sepa_mandate', null));

        static::assertSame(['error bankDetails.sepaMandate sepa_mandate.required'], DomainAssertions::summary($collector->result()));
        static::assertSame('The sepa mandate is required.', $collector->result()->all()[0]->message());
    }
}
