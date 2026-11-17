<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\DependencyInjection;

use LogicException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafTierceDeclarationPaje\Testing\SimulatedPajeGateway;

/**
 * Container factory of the simulated gateway: the runtime guard of the "gateway.simulated" rule for an environment that
 * is a placeholder at build time (the build-time rule only sees a literal "production"), ADR 0013.
 */
final class SimulatedGatewayFactory
{
    /**
     * @param string $environment the resolved "environment" option
     *
     * @throws LogicException when the environment is "production"
     */
    public static function create(string $environment): SimulatedPajeGateway
    {
        if (Environment::from($environment) === Environment::Production) {
            throw new LogicException('"gateway.simulated" is meant for tests and cannot be used with the production environment.');
        }

        return new SimulatedPajeGateway();
    }
}
