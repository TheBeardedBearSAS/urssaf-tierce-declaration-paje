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

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\RateLimiter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\DependencyInjection\ServiceIds;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\RateLimiter\SlidingWindowRateLimiter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\App\TestKernel;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Harness;

/**
 * The smallest value of every bounded option is accepted (the constraint is "at least", not "more than").
 */
final class BundleConfigurationBoundariesTest extends TestCase
{
    public function testTheSmallestValuesAreAccepted(): void
    {
        $harness = Harness::boot([
            'token_safety_margin' => 0,
            'http_timeout' => 0.1,
            'rate_limiter' => ['enabled' => true, 'limit' => 1, 'window' => 1],
            'lock' => ['enabled' => 'auto', 'ttl' => 1.0],
            'workflow' => ['max_submission_attempts' => 1, 'default_page_size' => 1, 'max_page_size' => 1],
        ]);

        static::assertSame(0, $harness->privateService(ServiceIds::API_CONFIGURATION, ApiConfiguration::class)->tokenSafetyMarginSeconds());
        static::assertInstanceOf(SlidingWindowRateLimiter::class, $harness->privateService(RateLimiter::class, RateLimiter::class));
        $policy = $harness->privateService(WorkflowPolicy::class, WorkflowPolicy::class);
        static::assertSame(1, $policy->maxSubmissionAttempts());
        static::assertSame(1, $policy->pageSize(null));
        static::assertSame(1, $policy->pageSize(50));
    }

    public function testTheDefaultPageSizeMayEqualTheMaximum(): void
    {
        $policy = Harness::boot(['workflow' => ['default_page_size' => 50, 'max_page_size' => 50]])->privateService(WorkflowPolicy::class, WorkflowPolicy::class);

        static::assertSame(50, $policy->pageSize(null));
    }

    public function testEveryLockModeIsAccepted(): void
    {
        foreach (['auto', true, false] as $mode) {
            static::assertTrue((new TestKernel(['lock' => ['enabled' => $mode]], false, true))->definitions()->hasDefinition(OperationLocks::class));
        }
    }
}
