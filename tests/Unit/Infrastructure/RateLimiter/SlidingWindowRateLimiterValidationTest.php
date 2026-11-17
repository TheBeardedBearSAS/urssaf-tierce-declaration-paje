<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\RateLimiter;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Clock\SystemClock;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\RateLimiter\SlidingWindowRateLimiter;

final class SlidingWindowRateLimiterValidationTest extends TestCase
{
    /**
     * @return iterable<string, array{int}>
     */
    public static function provideNonPositiveValues(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
    }

    #[DataProvider('provideNonPositiveValues')]
    public function testRejectsANonPositiveLimit(int $limit): void
    {
        $this->expectExceptionObject(InvalidValue::notPositive('rate_limit'));

        new SlidingWindowRateLimiter($limit, 10, new SystemClock(new MockClock()));
    }

    #[DataProvider('provideNonPositiveValues')]
    public function testRejectsANonPositiveWindow(int $window): void
    {
        $this->expectExceptionObject(InvalidValue::notPositive('rate_limit_window'));

        new SlidingWindowRateLimiter(10, $window, new SystemClock(new MockClock()));
    }
}
