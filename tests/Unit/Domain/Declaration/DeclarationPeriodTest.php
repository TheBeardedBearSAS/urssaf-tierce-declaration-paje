<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationPeriod;

final class DeclarationPeriodTest extends TestCase
{
    public function testExposesTheThreeDates(): void
    {
        $period = DeclarationSamples::period();

        static::assertSame('2026-10-01', $period->start()->toString());
        static::assertSame('2026-10-31', $period->end()->toString());
        static::assertSame('2026-11-05', $period->paymentDate()->toString());
    }

    public function testTheContractStatesNoOrderingRule(): void
    {
        $period = DeclarationPeriod::of(
            DeclarationSamples::date('2026-10-31'),
            DeclarationSamples::date('2026-10-01'),
            DeclarationSamples::date('2026-09-01'),
        );

        static::assertSame('2026-10-31', $period->start()->toString());
        static::assertSame('2026-10-01', $period->end()->toString());
        static::assertSame('2026-09-01', $period->paymentDate()->toString());
    }

    public function testEquality(): void
    {
        $period = DeclarationSamples::period();
        $date = DeclarationSamples::date(...);

        static::assertTrue($period->equals(DeclarationSamples::period()));
        static::assertFalse($period->equals(DeclarationPeriod::of($date('2026-10-02'), $date('2026-10-31'), $date('2026-11-05'))));
        static::assertFalse($period->equals(DeclarationPeriod::of($date('2026-10-01'), $date('2026-10-30'), $date('2026-11-05'))));
        static::assertFalse($period->equals(DeclarationPeriod::of($date('2026-10-01'), $date('2026-10-31'), $date('2026-11-06'))));
    }
}
