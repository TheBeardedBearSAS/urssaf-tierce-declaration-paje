<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Event;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\DeclarationAlreadyDeclared;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Event\Psr14IntegrationEventSink;

final class Psr14IntegrationEventSinkTest extends TestCase
{
    public function testDispatchesEachIntegrationEventThroughThePsr14Dispatcher(): void
    {
        $dispatcher = new RecordingDispatcher();
        $first = new DeclarationAlreadyDeclared('e1', '2026-11-15T10:00:00Z', 'tenant-1', 'record-1', 'declaration-1', null, 'childminder', '2026-10-01', '2026-10-31', ['ER_API_050_0070']);
        $second = new DeclarationAlreadyDeclared('e2', '2026-11-15T10:00:00Z', 'tenant-1', 'record-2', 'declaration-2', null, 'home_care', '2026-10-01', '2026-10-31', ['ER_API_DECLA_00020']);
        $sink = new Psr14IntegrationEventSink($dispatcher);

        $sink->dispatch($first);
        $sink->dispatch($second);

        static::assertSame([$first, $second], $dispatcher->dispatched());
    }
}
