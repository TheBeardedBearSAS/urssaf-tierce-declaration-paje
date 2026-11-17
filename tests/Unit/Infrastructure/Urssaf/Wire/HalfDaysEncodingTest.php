<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\HalfDays;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\HalfDaysEncoding;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonEncoder;

final class HalfDaysEncodingTest extends TestCase
{
    /**
     * @return iterable<string, array{int, string}>
     */
    public static function provideHalfDays(): iterable
    {
        yield 'zero' => [0, '0'];
        yield 'half a day' => [1, '0.5'];
        yield 'one day' => [2, '1'];
        yield 'one and a half' => [3, '1.5'];
        yield 'maximum of the contract' => [198, '99'];
    }

    #[DataProvider('provideHalfDays')]
    public function testEncodeIsAMultipleOfHalfADay(int $halfDays, string $literal): void
    {
        static::assertSame($literal, HalfDaysEncoding::encode(HalfDays::fromHalfDays($halfDays))->literal());
        static::assertSame($literal, JsonEncoder::encode(HalfDaysEncoding::encode(HalfDays::fromHalfDays($halfDays))));
    }

    public function testEveryEncodedValueIsAMultipleOfTheSwaggerStep(): void
    {
        for ($halfDays = 0; $halfDays <= 198; ++$halfDays) {
            $literal = HalfDaysEncoding::encode(HalfDays::fromHalfDays($halfDays))->literal();

            static::assertSame($halfDays, (int) round(\floatval($literal) * 2), $literal);
        }
    }
}
