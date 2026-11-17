<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\HalfDays;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\OptionalWireValues;

final class OptionalWireValuesTest extends TestCase
{
    public function testAnAmountIsEncodedWithTwoDecimals(): void
    {
        static::assertSame('12.50', OptionalWireValues::amount(Amount::fromDecimalString('12.5'))?->literal());
        static::assertSame('0.00', OptionalWireValues::amount(Amount::fromCents(0))?->literal());
    }

    public function testAnAbsentAmountStaysAbsent(): void
    {
        static::assertNull(OptionalWireValues::amount(null));
    }

    public function testHalfDaysAreEncodedInTheShortestForm(): void
    {
        static::assertSame('2.5', OptionalWireValues::halfDays(HalfDays::fromHalfDays(5))?->literal());
        static::assertSame('0', OptionalWireValues::halfDays(HalfDays::fromHalfDays(0))?->literal());
    }

    public function testAbsentHalfDaysStayAbsent(): void
    {
        static::assertNull(OptionalWireValues::halfDays(null));
    }

    public function testOnlyNullMembersAreDroppedAndTheOrderIsKept(): void
    {
        static::assertSame(
            ['b' => 0, 'a' => false, 'd' => '', 'e' => []],
            OptionalWireValues::present(['b' => 0, 'c' => null, 'a' => false, 'd' => '', 'e' => [], 'f' => null]),
        );
        static::assertSame([], OptionalWireValues::present(['a' => null]));
    }
}
