<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\DependencyInjection;

use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\FailureReporter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\Event\EmployerRegistered;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Logging\PsrLoggerFailureReporter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Scenario;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\ThrowingListener;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Fake\CapturingLogger;

/**
 * The booted application reports a failure after a commit and logs the URSSAF calls through its "logger" service.
 */
final class LoggingWiringTest extends TestCase
{
    public function testTheFailureReporterIsThePsrLoggerOne(): void
    {
        $harness = Harness::boot();

        static::assertInstanceOf(PsrLoggerFailureReporter::class, $harness->privateService(FailureReporter::class, FailureReporter::class));
    }

    public function testAnEventPublicationFailureAfterCommitIsLoggedAndTheStateIsKept(): void
    {
        $harness = Harness::boot();
        $harness->privateService('event_dispatcher', EventDispatcherInterface::class)->addListener(
            EmployerRegistered::class,
            new ThrowingListener(),
        );

        $account = Scenario::importedEmployer($harness);

        static::assertNotSame('', $account->accountId);
        $records = $harness->privateService('logger', CapturingLogger::class)->records();
        static::assertCount(1, $records);
        static::assertSame('error', $records[0]['level']);
        static::assertSame('Domain event publication failed after commit.', $records[0]['message']);
        static::assertSame(
            ['exception' => LogicException::class, 'message' => 'listener exploded', 'events' => [EmployerRegistered::class]],
            $records[0]['context'],
        );
    }

    public function testTheGatewayLogsThroughThePackageLogger(): void
    {
        $harness = Harness::boot();
        $logger = $harness->privateService('logger', CapturingLogger::class);

        Scenario::mandatedEmployer($harness);

        $messages = array_column($logger->records(), 'message');
        static::assertContains('URSSAF token request.', $messages);
        static::assertContains('URSSAF call.', $messages);
    }
}
