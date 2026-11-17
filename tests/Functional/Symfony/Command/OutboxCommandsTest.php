<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\MandateRegistered;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\HeldLocks;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Output;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Scenario;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\ThrowingListener;

final class OutboxCommandsTest extends TestCase
{
    private const OUTBOX = ['outbox' => ['enabled' => true, 'sink' => 'event_dispatcher']];

    public function testThePendingEventsAreDelivered(): void
    {
        $harness = Harness::boot(self::OUTBOX);
        Scenario::mandatedEmployer($harness);
        $command = $harness->command('urssaf:paje:outbox:relay');

        static::assertSame(0, $command->execute([]));

        $rows = Output::rows($command->getDisplay());
        static::assertSame(['Dispatched', 'Failed', 'Deferred'], $rows[0]);
        static::assertNotSame('0', $rows[1][0]);
        static::assertSame(['0', '0'], \array_slice($rows[1], 1));
    }

    public function testNothingPendingIsASuccess(): void
    {
        $command = Harness::boot(self::OUTBOX)->command('urssaf:paje:outbox:relay');

        static::assertSame(0, $command->execute([]));

        static::assertSame([['Dispatched', 'Failed', 'Deferred'], ['0', '0', '0']], Output::rows($command->getDisplay()));
    }

    public function testAFailedDeliveryExitsWithOne(): void
    {
        $harness = Harness::boot(self::OUTBOX);
        $harness->privateService('event_dispatcher', EventDispatcherInterface::class)->addListener(MandateRegistered::class, new ThrowingListener());
        Scenario::mandatedEmployer($harness);
        $command = $harness->command('urssaf:paje:outbox:relay');

        static::assertSame(1, $command->execute([]));

        static::assertSame('1', Output::rows($command->getDisplay())[1][1]);
        static::assertStringNotContainsString('listener exploded', $command->getDisplay());
    }

    public function testTheLimitCapsTheDelivery(): void
    {
        $harness = Harness::boot(self::OUTBOX);
        Scenario::mandatedEmployer($harness);
        $command = $harness->command('urssaf:paje:outbox:relay');

        static::assertSame(0, $command->execute(['--limit' => '1']));

        static::assertSame('1', Output::rows($command->getDisplay())[1][0]);
    }

    public function testAnInvalidLimitExitsWithTwo(): void
    {
        $command = Harness::boot(self::OUTBOX)->command('urssaf:paje:outbox:relay');

        static::assertSame(2, $command->execute(['--limit' => '-1']));
    }

    public function testARunningRelayIsSkipped(): void
    {
        $harness = Harness::boot(self::OUTBOX, false, true);
        Scenario::mandatedEmployer($harness);
        $held = HeldLocks::hold($harness, OperationLocks::resource('outbox.relay', '*'));
        $command = $harness->command('urssaf:paje:outbox:relay');

        static::assertSame(0, $command->execute([]));

        static::assertStringContainsString('Already running (lock "urssaf_paje.outbox.relay.*" is held): skipped.', $command->getDisplay());
        $held->release();
    }

    public function testTheDispatchedEntriesAreDeletedAfterTheRetention(): void
    {
        $harness = Harness::boot(self::OUTBOX);
        Scenario::mandatedEmployer($harness);
        static::assertSame(0, $harness->command('urssaf:paje:outbox:relay')->execute([]));
        $early = $harness->command('urssaf:paje:outbox:purge');
        static::assertSame(0, $early->execute([]));
        static::assertStringContainsString('0 dispatched outbox entries deleted.', $early->getDisplay());
        $harness->clock->modify('+31 days');
        $late = $harness->command('urssaf:paje:outbox:purge');

        static::assertSame(0, $late->execute([]));

        static::assertMatchesRegularExpression('/^[1-9][0-9]* dispatched outbox entries deleted\.$/m', $late->getDisplay());
    }

    public function testTheRetentionIsTheConfiguredOne(): void
    {
        $harness = Harness::boot(['outbox' => ['enabled' => true, 'sink' => 'event_dispatcher', 'retention' => 'P1D']]);
        Scenario::mandatedEmployer($harness);
        static::assertSame(0, $harness->command('urssaf:paje:outbox:relay')->execute([]));
        $harness->clock->modify('+2 days');
        $command = $harness->command('urssaf:paje:outbox:purge');

        static::assertSame(0, $command->execute([]));

        static::assertStringNotContainsString('0 dispatched', $command->getDisplay());
    }
}
