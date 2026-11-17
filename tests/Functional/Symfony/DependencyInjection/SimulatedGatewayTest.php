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
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\DeclareSalary;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\DeclareSalaryHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RegisterMandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RegisterMandateHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\TrackEmployer;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\TrackEmployerHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\EmployerSelector;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\PajeGateway;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\UrssafHttpGateway;
use TheBeardedBear\UrssafTierceDeclarationPaje\Testing\SimulatedPajeGateway;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Testing\SimulatorSamples;

/**
 * "gateway.simulated: true": the whole workflow runs against the in-memory URSSAF platform, as in the tests of a host.
 */
final class SimulatedGatewayTest extends TestCase
{
    public function testTheRealGatewayIsUsedByDefault(): void
    {
        $harness = Harness::boot();

        static::assertInstanceOf(UrssafHttpGateway::class, $harness->privateService(PajeGateway::class, PajeGateway::class));
        static::assertFalse($harness->has(SimulatedPajeGateway::class));
    }

    public function testTheWorkflowRunsAgainstTheSimulatorWithoutNetwork(): void
    {
        $harness = Harness::boot(['gateway' => ['simulated' => true]]);
        $urssaf = $harness->get(SimulatedPajeGateway::class);
        static::assertSame($urssaf, $harness->privateService(PajeGateway::class, PajeGateway::class));
        $employer = SimulatorSamples::employer();
        $urssaf->knowEmployer($employer);
        $tenant = SimulatorSamples::tenant();
        $reference = ExternalReference::fromString('employer-1');

        $harness->get(TrackEmployerHandler::class)(new TrackEmployer($tenant, $reference, $employer));
        $harness->get(RegisterMandateHandler::class)(new RegisterMandate($tenant, EmployerSelector::byExternalReference($reference)));
        $submission = $harness->get(DeclareSalaryHandler::class)(new DeclareSalary($tenant, ExternalReference::fromString('declaration-1'), $employer, SimulatorSamples::childminder()));

        static::assertSame(ItemOutcome::Declared, $submission->outcome);
        static::assertSame([Operation::VerifyEmployer, Operation::RegisterMandate, Operation::DeclareChildminder], $urssaf->calls());
        static::assertSame([], $harness->client->getRequests());
    }

    public function testTheSimulatorIsRefusedWhenAPlaceholderResolvesToProduction(): void
    {
        $_ENV['URSSAF_TEST_ENVIRONMENT'] = 'production';
        $_SERVER['URSSAF_TEST_ENVIRONMENT'] = 'production';
        try {
            $harness = Harness::boot(['environment' => '%env(URSSAF_TEST_ENVIRONMENT)%', 'gateway' => ['simulated' => true]]);

            $this->expectException(LogicException::class);
            $this->expectExceptionMessage('"gateway.simulated" is meant for tests and cannot be used with the production environment.');

            $harness->get(SimulatedPajeGateway::class);
        } finally {
            unset($_ENV['URSSAF_TEST_ENVIRONMENT'], $_SERVER['URSSAF_TEST_ENVIRONMENT']);
        }
    }

    public function testTheSimulatorIsAcceptedWhenAPlaceholderResolvesToTheSandbox(): void
    {
        $_ENV['URSSAF_TEST_ENVIRONMENT'] = 'sandbox';
        $_SERVER['URSSAF_TEST_ENVIRONMENT'] = 'sandbox';
        try {
            $harness = Harness::boot(['environment' => '%env(URSSAF_TEST_ENVIRONMENT)%', 'gateway' => ['simulated' => true]]);

            static::assertInstanceOf(SimulatedPajeGateway::class, $harness->get(SimulatedPajeGateway::class));
        } finally {
            unset($_ENV['URSSAF_TEST_ENVIRONMENT'], $_SERVER['URSSAF_TEST_ENVIRONMENT']);
        }
    }
}
