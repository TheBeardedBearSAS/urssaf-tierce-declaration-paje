<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\DependencyInjection\ClockFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\UtcSystemClock;

final class ClockFactoryTest extends TestCase
{
    public function testTheClockOfTheApplicationIsUsedWhenThereIsOne(): void
    {
        $clock = new MockClock('2026-11-15 10:00:00 UTC');

        static::assertSame('2026-11-15T10:00:00+00:00', ClockFactory::create($clock)->now()->format(DATE_ATOM));
    }

    public function testTheUtcSystemClockIsTheFallback(): void
    {
        static::assertInstanceOf(UtcSystemClock::class, ClockFactory::create(null));
    }
}
