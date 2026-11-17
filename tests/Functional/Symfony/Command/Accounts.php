<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Command;

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ImportEmployer;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ImportEmployerHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\EmployerAccountView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Testing\SimulatorSamples;

/**
 * Configurations shared by the command tests.
 */
final class Accounts
{
    public const SECOND_TENANT = 'tenant-2';

    private function __construct() {}

    /**
     * A second employer (another Pajemploi number) of the tenant, tracked without any call.
     */
    public static function importOtherEmployer(Harness $harness, string $tenant, string $reference): EmployerAccountView
    {
        return $harness->get(ImportEmployerHandler::class)(
            new ImportEmployer(TenantId::fromString($tenant), ExternalReference::fromString($reference), SimulatorSamples::employer(SimulatorSamples::OTHER_EMPLOYER_NUMBER)),
        );
    }

    /**
     * Configuration of a second tenant (the secret is an environment placeholder, set by the test kernel).
     *
     * @return array<string, mixed>
     */
    public static function withSecondTenant(): array
    {
        // @mago-expect lint:no-literal-password The secret is an environment placeholder, set by the test kernel.
        return ['credentials' => [self::SECOND_TENANT => ['client_id' => 'client-2', 'client_secret' => '%env(URSSAF_TEST_SECRET)%']]];
    }
}
