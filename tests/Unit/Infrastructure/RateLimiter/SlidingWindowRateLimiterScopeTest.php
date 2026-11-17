<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\RateLimiter;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Clock\SystemClock;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\RateLimiter\SlidingWindowRateLimiter;

/**
 * What the quota is counted over: one tenant, every operation together (ADR 0011).
 */
final class SlidingWindowRateLimiterScopeTest extends TestCase
{
    private const START = '2026-01-01T10:00:00+00:00';

    public function testQuotaIsSharedByAllOperationsOfATenant(): void
    {
        $limiter = new SlidingWindowRateLimiter(3, 10, new SystemClock(new MockClock(self::START)));
        $tenant = TenantId::fromString('tenant-a');
        $limiter->acquire($tenant, Operation::VerifyEmployer);
        $limiter->acquire($tenant, Operation::DeclareChildminder);
        $limiter->acquire($tenant, Operation::RegisterMandate);

        $this->expectExceptionObject(RateLimitExceeded::forOperation(Operation::VerifyEmployee, 10));

        $limiter->acquire($tenant, Operation::VerifyEmployee);
    }

    public function testTenantsAreIsolated(): void
    {
        $limiter = new SlidingWindowRateLimiter(1, 10, new SystemClock(new MockClock(self::START)));
        $limiter->acquire(TenantId::fromString('tenant-a'), Operation::VerifyEmployer);

        $limiter->acquire(TenantId::fromString('tenant-b'), Operation::VerifyEmployer);

        $this->expectException(RateLimitExceeded::class);

        $limiter->acquire(TenantId::fromString('tenant-a'), Operation::VerifyEmployer);
    }

    public function testExceptionNamesTheOperationOfTheRefusedCall(): void
    {
        $limiter = new SlidingWindowRateLimiter(1, 10, new SystemClock(new MockClock(self::START)));
        $tenant = TenantId::fromString('tenant-a');
        $limiter->acquire($tenant, Operation::VerifyEmployer);

        try {
            $limiter->acquire($tenant, Operation::CancelMandate);
            static::fail('The limit should be exceeded.');
        } catch (RateLimitExceeded $exception) {
            static::assertSame(Operation::CancelMandate, $exception->operation());
        }
    }

    public function testLimitAndWindowOfOneAreAccepted(): void
    {
        $limiter = new SlidingWindowRateLimiter(1, 1, new SystemClock(new MockClock(self::START)));
        $tenant = TenantId::fromString('tenant-a');

        $limiter->acquire($tenant, Operation::VerifyEmployer);

        $this->expectExceptionObject(RateLimitExceeded::forOperation(Operation::VerifyEmployer, 1));

        $limiter->acquire($tenant, Operation::VerifyEmployer);
    }
}
