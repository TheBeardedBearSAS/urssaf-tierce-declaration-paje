<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Scheduler;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Schedule;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Scheduler\UrssafScheduleProvider;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\App\TestKernel;

final class UrssafScheduleProviderTest extends TestCase
{
    public function testEveryCronOfThePackageIsScheduledAtItsFrequency(): void
    {
        $schedule = (new UrssafScheduleProvider([
            'sync_employer_statuses' => 'PT1H',
            'reconcile_stale_declarations' => 'PT15M',
            'relay_outbox' => 'PT1M',
            'purge_outbox' => 'P1D',
        ]))->getSchedule();

        static::assertSame([
            'urssaf:paje:employers:sync-status every 3600 seconds (PT1H)',
            'urssaf:paje:declarations:reconcile-stale every 900 seconds (PT15M)',
            'urssaf:paje:outbox:relay every 60 seconds (PT1M)',
            'urssaf:paje:outbox:purge every 86400 seconds (P1D)',
        ], self::describe($schedule));
    }

    public function testATaskWithoutFrequencyIsLeftOut(): void
    {
        $schedule = (new UrssafScheduleProvider([
            'sync_employer_statuses' => null,
            'reconcile_stale_declarations' => 'PT30M',
            'relay_outbox' => null,
        ]))->getSchedule();

        static::assertSame(['urssaf:paje:declarations:reconcile-stale every 1800 seconds (PT30M)'], self::describe($schedule));
    }

    public function testAnUnknownOrMissingTaskIsIgnored(): void
    {
        static::assertSame([], self::describe((new UrssafScheduleProvider([]))->getSchedule()));
        static::assertSame([], self::describe((new UrssafScheduleProvider(['unknown_task' => 'PT1M']))->getSchedule()));
    }

    public function testTheCommandsAreTheOnesOfTheBundleAndNoDeclarationIsSubmitted(): void
    {
        $commands = array_merge(...array_values(UrssafScheduleProvider::TASKS));

        foreach ($commands as $command) {
            static::assertStringStartsWith('urssaf:paje:', $command);
        }
        static::assertSame([], array_values(array_filter($commands, static fn(string $command): bool => str_contains($command, 'requeue') || str_contains($command, 'declare'))));
        static::assertSame(['sync_employer_statuses', 'reconcile_stale_declarations', 'relay_outbox', 'purge_outbox'], array_keys(UrssafScheduleProvider::TASKS));
    }

    public function testTheProviderIsRegisteredUnderTheConfiguredScheduleName(): void
    {
        $enabled = (new TestKernel(['scheduler' => ['enabled' => true, 'name' => 'billing', 'frequencies' => ['sync_employer_statuses' => 'PT30M', 'reconcile_stale_declarations' => null]]]))->definitions();
        $disabled = (new TestKernel())->definitions();

        $definition = $enabled->getDefinition(UrssafScheduleProvider::class);
        static::assertSame(['scheduler.schedule_provider' => [['name' => 'billing']]], $definition->getTags());
        static::assertEquals([['sync_employer_statuses' => 'PT30M', 'reconcile_stale_declarations' => null, 'relay_outbox' => null, 'purge_outbox' => null]], $definition->getArguments());
        static::assertFalse($disabled->hasDefinition(UrssafScheduleProvider::class));
    }

    public function testTheOutboxCommandsAreScheduledOnlyWithTheOutbox(): void
    {
        $kernel = new TestKernel(['scheduler' => ['enabled' => true], 'outbox' => ['enabled' => true]]);

        $definition = $kernel->definitions()->getDefinition(UrssafScheduleProvider::class);

        static::assertSame(['scheduler.schedule_provider' => [['name' => 'urssaf_paje']]], $definition->getTags());
        static::assertEquals([['sync_employer_statuses' => 'P1D', 'reconcile_stale_declarations' => 'PT15M', 'relay_outbox' => 'PT1M', 'purge_outbox' => 'P1D']], $definition->getArguments());
    }

    /**
     * @return list<string> "<command> <trigger>" of every recurring message
     */
    private static function describe(Schedule $schedule): array
    {
        $described = [];
        foreach ($schedule->getRecurringMessages() as $recurring) {
            $context = new MessageContext('urssaf_paje', $recurring->getId(), $recurring->getTrigger(), new DateTimeImmutable());
            $messages = iterator_to_array($recurring->getProvider()->getMessages($context), false);
            static::assertCount(1, $messages);
            $message = reset($messages);
            static::assertInstanceOf(RunCommandMessage::class, $message);
            $described[] = $message->input . ' ' . $recurring->getTrigger()->__toString();
        }

        return $described;
    }
}
