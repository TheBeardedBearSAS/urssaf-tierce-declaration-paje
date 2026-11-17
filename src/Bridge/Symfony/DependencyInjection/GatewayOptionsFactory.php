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

use Psr\Log\LoggerInterface;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\IdGenerator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\RateLimiter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\GatewayOptions;

/**
 * Container factory of the options of the HTTP gateway ({@see GatewayOptions} is immutable and has no public
 * constructor): the collaborators of the container, no logging without a "logger" service.
 */
final class GatewayOptionsFactory
{
    public static function create(Clock $clock, IdGenerator $ids, RateLimiter $rateLimiter, ?LoggerInterface $logger): GatewayOptions
    {
        $options = GatewayOptions::defaults()->withClock($clock)->withIdGenerator($ids)->withRateLimiter($rateLimiter);

        return $logger instanceof LoggerInterface ? $options->withLogger($logger) : $options;
    }
}
