<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Port;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RetryableException;

final class RateLimitExceededTest extends TestCase
{
    public function testMessageAndAccessors(): void
    {
        $exception = RateLimitExceeded::forOperation(Operation::DeclareChildminder, 12);

        static::assertSame(Operation::DeclareChildminder, $exception->operation());
        static::assertSame(12, $exception->retryAfterSeconds());
        static::assertSame(
            'Local rate limit reached for URSSAF method 050; retry in 12 second(s).',
            $exception->getMessage(),
        );
        static::assertContains(RetryableException::class, class_implements($exception));
    }

    public function testNegativeDelaysAreClampedToZero(): void
    {
        $exception = RateLimitExceeded::forOperation(Operation::RegisterMandate, -3);

        static::assertSame(0, $exception->retryAfterSeconds());
        static::assertSame(
            'Local rate limit reached for URSSAF method 030; retry in 0 second(s).',
            $exception->getMessage(),
        );
        static::assertSame(0, RateLimitExceeded::forOperation(Operation::RegisterMandate, 0)->retryAfterSeconds());
    }
}
