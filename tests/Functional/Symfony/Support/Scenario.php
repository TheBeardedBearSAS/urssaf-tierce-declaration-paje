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

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\DeclareSalary;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\DeclareSalaryHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ImportEmployer;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ImportEmployerHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RegisterMandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RegisterMandateHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\DeclarationSubmission;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\EmployerAccountView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\EmployerSelector;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\IdGenerator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationSnapshot;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Stack;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Testing\SimulatorSamples;

/**
 * Drives an application through the handlers to a given state, with the fictitious employer and the childminder
 * declaration of the simulator samples (the scripted URSSAF platform answers them). The scripted routes touched by a
 * scenario are put back to their default answer afterwards.
 */
final class Scenario
{
    public const TENANT = 'tenant-1';
    public const EMPLOYER = 'employer-1';
    public const DECLARATION = 'declaration-1';

    private function __construct() {}

    /**
     * Employer tracked in stage "tracked" (no URSSAF call).
     */
    public static function importedEmployer(Harness $harness, string $tenant = self::TENANT, string $reference = self::EMPLOYER): EmployerAccountView
    {
        return $harness->get(ImportEmployerHandler::class)(
            new ImportEmployer(TenantId::fromString($tenant), ExternalReference::fromString($reference), SimulatorSamples::employer()),
        );
    }

    /**
     * Imported employer whose mandate was registered (method 030, route 030). Returns the account as imported.
     */
    public static function mandatedEmployer(Harness $harness, string $tenant = self::TENANT, string $reference = self::EMPLOYER): EmployerAccountView
    {
        $account = self::importedEmployer($harness, $tenant, $reference);
        $harness->get(RegisterMandateHandler::class)(new RegisterMandate(
            TenantId::fromString($tenant),
            EmployerSelector::byExternalReference(ExternalReference::fromString($reference)),
        ));

        return $account;
    }

    /**
     * Declaration in state "declared" (method 050 answered the success fixture).
     */
    public static function declaration(Harness $harness, string $reference = self::DECLARATION): DeclarationSubmission
    {
        self::mandatedEmployer($harness);

        return self::declare($harness, $reference);
    }

    /**
     * Declaration in state "outcome_unknown": the platform answered a 500 to method 050 (never resent, ADR 0003).
     */
    public static function outcomeUnknownDeclaration(Harness $harness, string $reference = self::DECLARATION): DeclarationSubmission
    {
        self::mandatedEmployer($harness);
        $harness->urssaf->respond(HttpScript::ROUTE_050, 500, '{"message":"Internal Server Error"}');
        $submission = self::declare($harness, $reference);
        $harness->urssaf->respondWithFixture(HttpScript::ROUTE_050, 200, 'declare_response_valid.json');

        return $submission;
    }

    /**
     * Declaration in state "invalid_credentials": the cached access token expired and the token route answers a 401.
     * The clock is two hours later afterwards.
     */
    public static function invalidCredentialsDeclaration(Harness $harness, string $reference = self::DECLARATION): DeclarationSubmission
    {
        self::mandatedEmployer($harness);
        $harness->clock->modify('+2 hours');
        $harness->urssaf->respond(HttpScript::ROUTE_AUTHENTICATION, 401, '{"error":"invalid_client"}');
        $submission = self::declare($harness, $reference);
        $harness->urssaf->respond(HttpScript::ROUTE_AUTHENTICATION, 200, Stack::TOKEN_RESPONSE);

        return $submission;
    }

    /**
     * Record in state "submitting" claimed twenty minutes ago (the stale threshold is fifteen): nothing was sent.
     * The clock is twenty minutes later afterwards.
     */
    public static function staleDeclaration(Harness $harness, string $tenant = self::TENANT, string $reference = self::DECLARATION): DeclarationRecordId
    {
        return self::staleSnapshot($harness, DeclarationSnapshot::fromDeclaration(SimulatorSamples::employerNumber(), SimulatorSamples::childminder()), $tenant, $reference);
    }

    /**
     * Same as {@see staleDeclaration()}, with the content of the record given.
     */
    public static function staleSnapshot(Harness $harness, DeclarationSnapshot $snapshot, string $tenant = self::TENANT, string $reference = self::DECLARATION): DeclarationRecordId
    {
        $records = $harness->privateService(DeclarationRecordRepository::class, DeclarationRecordRepository::class);
        $now = Instant::fromDateTime($harness->clock->now());
        $id = DeclarationRecordId::fromString($harness->privateService(IdGenerator::class, IdGenerator::class)->generate());
        $records->save(DeclarationRecord::record(
            $id,
            TenantId::fromString($tenant),
            ExternalReference::fromString($reference),
            $snapshot,
            $now,
            null,
        ));
        $records->claimForSubmission($id, $now);
        $harness->clock->modify('+20 minutes');

        return $id;
    }

    private static function declare(Harness $harness, string $reference): DeclarationSubmission
    {
        return $harness->get(DeclareSalaryHandler::class)(
            new DeclareSalary(TenantId::fromString(self::TENANT), ExternalReference::fromString($reference), SimulatorSamples::employer(), SimulatorSamples::childminder()),
        );
    }
}
