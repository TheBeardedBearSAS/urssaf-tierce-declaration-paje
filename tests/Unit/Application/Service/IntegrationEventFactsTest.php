<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Service;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\IntegrationEventFacts;

/**
 * @internal
 */
final class IntegrationEventFactsTest extends TestCase
{
    public function testTheIdIsAUuidShapedSliceOfTheHashOfTheFact(): void
    {
        $at = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $hash = hash('sha256', serialize(['type', 'aggregate', IntegrationEventFacts::instant($at), ['x']]));

        static::assertSame(
            substr($hash, 0, 8) . '-' . substr($hash, 8, 4) . '-' . substr($hash, 12, 4) . '-' . substr($hash, 16, 4) . '-' . substr($hash, 20, 12),
            IntegrationEventFacts::id('type', 'aggregate', $at, ['x']),
        );
        static::assertSame('4297d0dd-635c-899e-16a6-e9f711ad97fe', IntegrationEventFacts::id('type', 'aggregate', $at, ['x']));
    }

    public function testTheTypeContributesToTheId(): void
    {
        $at = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

        static::assertNotSame(
            IntegrationEventFacts::id('type-a', 'aggregate', $at, ['x']),
            IntegrationEventFacts::id('type-b', 'aggregate', $at, ['x']),
        );
    }
}
