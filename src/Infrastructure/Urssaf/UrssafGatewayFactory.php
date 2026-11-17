<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf;

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\TokenStore;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\PajeGateway;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\RateLimiter\NoopRateLimiter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Http\JsonApiClient;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Http\OAuth2TokenProvider;

/**
 * Public entry point to build the URSSAF HTTP gateway outside a framework bridge (ADR 0000, ADR 0002):
 *
 *     $gateway = UrssafGatewayFactory::create(
 *         new HttpTransport($psr18Client, $psr17RequestFactory, $psr17StreamFactory),
 *         $credentialsProvider,
 *         $tokenStore,
 *         ApiConfiguration::forEnvironment(Environment::Production),
 *         GatewayOptions::defaults()->withLogger($logger),
 *     );
 *
 * Without options the gateway does not throttle locally ({@see NoopRateLimiter}, ADR 0011), does not log and reads the
 * system clock.
 *
 * The OAuth2 token provider and the JSON client it wires are internal.
 */
final class UrssafGatewayFactory
{
    public static function create(
        HttpTransport $transport,
        CredentialsProvider $credentials,
        TokenStore $tokens,
        ApiConfiguration $configuration,
        ?GatewayOptions $options = null,
    ): PajeGateway {
        $options ??= GatewayOptions::defaults();

        $client = new JsonApiClient(
            $transport,
            new OAuth2TokenProvider($transport, $tokens, $configuration, $options),
            $credentials,
            $configuration,
            $options,
        );

        return new UrssafHttpGateway($client, $configuration);
    }
}
