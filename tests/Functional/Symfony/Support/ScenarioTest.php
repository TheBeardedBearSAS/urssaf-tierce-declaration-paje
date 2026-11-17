<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Testing\SimulatorSamples;

/**
 * Each scenario reaches its state through the real handlers and the scripted platform.
 */
final class ScenarioTest extends TestCase
{
    public function testAnImportedEmployerIsTrackedWithoutAnyCall(): void
    {
        $harness = Harness::boot();

        $account = Scenario::importedEmployer($harness);

        static::assertSame('tracked', $account->stage);
        static::assertSame(Scenario::EMPLOYER, $account->externalReference);
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_AUTHENTICATION));
    }

    public function testAMandatedEmployerHasAnActiveMandate(): void
    {
        $harness = Harness::boot();

        Scenario::mandatedEmployer($harness);

        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_030));
        $mandate = $harness->privateService(MandateRepository::class, MandateRepository::class)
            ->findActive(TenantId::fromString(Scenario::TENANT), SimulatorSamples::employerNumber());
        static::assertNotNull($mandate);
    }

    public function testADeclarationIsDeclared(): void
    {
        $harness = Harness::boot();

        $submission = Scenario::declaration($harness);

        static::assertSame(ItemOutcome::Declared, $submission->outcome);
        static::assertSame(DeclarationState::Declared->value, $submission->declaration->status->state);
        static::assertSame(Scenario::DECLARATION, $submission->declaration->externalReference);
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_050));
    }

    public function testAnOutcomeUnknownDeclarationFollowsAServerError(): void
    {
        $harness = Harness::boot();

        $submission = Scenario::outcomeUnknownDeclaration($harness);

        static::assertSame(DeclarationState::OutcomeUnknown->value, $submission->declaration->status->state);
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_050));
        // The route answers a success again.
        static::assertSame(ItemOutcome::Declared, Scenario::declaration($harness, 'declaration-2')->outcome);
    }

    public function testAnInvalidCredentialsDeclarationFollowsARefusedToken(): void
    {
        $harness = Harness::boot();

        $submission = Scenario::invalidCredentialsDeclaration($harness);

        static::assertSame(DeclarationState::InvalidCredentials->value, $submission->declaration->status->state);
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_050));
        static::assertSame(2, $harness->urssaf->calls(HttpScript::ROUTE_AUTHENTICATION));
    }

    public function testAStaleDeclarationIsSubmittingAndOlderThanThePolicy(): void
    {
        $harness = Harness::boot();

        $id = Scenario::staleDeclaration($harness);

        $records = $harness->privateService(DeclarationRecordRepository::class, DeclarationRecordRepository::class);
        static::assertSame(DeclarationState::Submitting, $records->get($id)->state());
        $policy = $harness->privateService(WorkflowPolicy::class, WorkflowPolicy::class);
        $stale = $records->findStaleSubmissions(Instant::fromDateTime($harness->clock->now()->sub($policy->staleSubmissionAfter())), 10);
        static::assertCount(1, $stale);
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_050));
    }
}
