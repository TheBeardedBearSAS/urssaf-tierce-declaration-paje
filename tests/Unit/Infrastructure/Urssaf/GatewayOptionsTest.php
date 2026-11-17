<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Clock\FrozenClock;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Id\RandomUuidGenerator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\RateLimiter\NoopRateLimiter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\GatewayOptions;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\UtcSystemClock;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Fake\CapturingLogger;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Fake\RecordingRateLimiter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Fake\SequenceIdGenerator;

final class GatewayOptionsTest extends TestCase
{
    public function testDefaultsAreNoThrottlingRandomIdsSystemClockAndNoLogging(): void
    {
        $options = GatewayOptions::defaults();

        static::assertInstanceOf(NoopRateLimiter::class, $options->rateLimiter());
        static::assertInstanceOf(RandomUuidGenerator::class, $options->idGenerator());
        static::assertInstanceOf(UtcSystemClock::class, $options->clock());
        static::assertInstanceOf(NullLogger::class, $options->logger());
        static::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $options->idGenerator()->generate());
    }

    public function testEachWitherSetsOnlyItsOwnValue(): void
    {
        $defaults = GatewayOptions::defaults();
        $limiter = new RecordingRateLimiter();
        $ids = new SequenceIdGenerator();
        $clock = new FrozenClock(new DateTimeImmutable('2026-11-15T10:00:00Z'));
        $logger = new CapturingLogger();

        $withLimiter = $defaults->withRateLimiter($limiter);
        static::assertSame($limiter, $withLimiter->rateLimiter());
        static::assertSame($defaults->idGenerator(), $withLimiter->idGenerator());
        static::assertSame($defaults->clock(), $withLimiter->clock());
        static::assertSame($defaults->logger(), $withLimiter->logger());

        $withIds = $defaults->withIdGenerator($ids);
        static::assertSame($defaults->rateLimiter(), $withIds->rateLimiter());
        static::assertSame($ids, $withIds->idGenerator());
        static::assertSame($defaults->clock(), $withIds->clock());
        static::assertSame($defaults->logger(), $withIds->logger());

        $withClock = $defaults->withClock($clock);
        static::assertSame($defaults->rateLimiter(), $withClock->rateLimiter());
        static::assertSame($defaults->idGenerator(), $withClock->idGenerator());
        static::assertSame($clock, $withClock->clock());
        static::assertSame($defaults->logger(), $withClock->logger());

        $withLogger = $defaults->withLogger($logger);
        static::assertSame($defaults->rateLimiter(), $withLogger->rateLimiter());
        static::assertSame($defaults->idGenerator(), $withLogger->idGenerator());
        static::assertSame($defaults->clock(), $withLogger->clock());
        static::assertSame($logger, $withLogger->logger());
    }

    public function testWithersChainAndKeepTheOthers(): void
    {
        $limiter = new RecordingRateLimiter();
        $ids = new SequenceIdGenerator();
        $clock = new FrozenClock(new DateTimeImmutable('2026-11-15T10:00:00Z'));
        $logger = new CapturingLogger();

        $options = GatewayOptions::defaults()
            ->withRateLimiter($limiter)
            ->withIdGenerator($ids)
            ->withClock($clock)
            ->withLogger($logger);

        static::assertSame($limiter, $options->rateLimiter());
        static::assertSame($ids, $options->idGenerator());
        static::assertSame($clock, $options->clock());
        static::assertSame($logger, $options->logger());
    }

    public function testOptionsAreImmutable(): void
    {
        $defaults = GatewayOptions::defaults();
        $logger = $defaults->logger();

        $defaults->withLogger(new CapturingLogger());

        static::assertSame($logger, $defaults->logger());
    }

    public function testTheDefaultClockIsUtcAndCurrent(): void
    {
        $before = time();
        $now = GatewayOptions::defaults()->clock()->now();

        static::assertSame('UTC', $now->getTimezone()->getName());
        static::assertGreaterThanOrEqual($before, $now->getTimestamp());
        static::assertLessThanOrEqual(time() + 1, $now->getTimestamp());
    }
}
