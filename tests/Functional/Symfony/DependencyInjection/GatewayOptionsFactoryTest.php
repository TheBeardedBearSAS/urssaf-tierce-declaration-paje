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

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\DependencyInjection\GatewayOptionsFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Clock\FrozenClock;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\RateLimiter\NoopRateLimiter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\SequentialIdGenerator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Fake\CapturingLogger;

/**
 * The options of the HTTP gateway take the collaborators of the container, and log only when there is a logger.
 */
final class GatewayOptionsFactoryTest extends TestCase
{
    public function testTheOptionsCarryTheCollaborators(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2026-11-15T10:00:00+00:00'));
        $ids = new SequentialIdGenerator('id-');
        $limiter = new NoopRateLimiter();
        $logger = new CapturingLogger();

        $options = GatewayOptionsFactory::create($clock, $ids, $limiter, $logger);

        static::assertSame($clock, $options->clock());
        static::assertSame($ids, $options->idGenerator());
        static::assertSame($limiter, $options->rateLimiter());
        static::assertSame($logger, $options->logger());
    }

    public function testThereIsNoLoggingWithoutALogger(): void
    {
        $options = GatewayOptionsFactory::create(new FrozenClock(new DateTimeImmutable('2026-11-15T10:00:00+00:00')), new SequentialIdGenerator('id-'), new NoopRateLimiter(), null);

        static::assertInstanceOf(NullLogger::class, $options->logger());
    }
}
