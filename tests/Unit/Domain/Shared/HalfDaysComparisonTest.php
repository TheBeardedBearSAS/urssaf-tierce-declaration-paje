<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Shared;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\HalfDays;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

final class HalfDaysComparisonTest extends TestCase
{
    public function testComparisons(): void
    {
        $two = HalfDays::fromHalfDays(4);

        static::assertTrue($two->isGreaterThan(HalfDays::fromHalfDays(3)));
        static::assertFalse($two->isGreaterThan(HalfDays::fromHalfDays(4)));
        static::assertFalse($two->isGreaterThan(HalfDays::fromHalfDays(5)));
        static::assertTrue($two->equals(HalfDays::fromHalfDays(4)));
        static::assertFalse($two->equals(HalfDays::fromHalfDays(3)));
        static::assertFalse($two->equals(HalfDays::fromHalfDays(5)));
    }

    public function testAssertAtMost(): void
    {
        $max = HalfDays::fromDecimalString('30');

        $atMax = HalfDays::fromDecimalString('30');
        static::assertSame($atMax, $atMax->assertAtMost($max, 'paid_leave_days'));
        static::assertSame(59, HalfDays::fromHalfDays(59)->assertAtMost($max, 'paid_leave_days')->halfDays());
    }

    public function testAssertAtMostRejectsGreaterValues(): void
    {
        $max = HalfDays::fromDecimalString('30');

        $exception = DomainAssertions::assertViolationCode(
            'paid_leave_days.out_of_range',
            static fn(): HalfDays => HalfDays::fromHalfDays(61)->assertAtMost($max, 'paid_leave_days'),
        );

        static::assertSame(['min' => '0', 'max' => '30'], $exception->params());
        static::assertSame('Invalid paid_leave_days: the value must be between 0 and 30.', $exception->getMessage());
    }
}
