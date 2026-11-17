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

final class SlidingWindowRateLimiterTest extends TestCase
{
    private const START = '2026-01-01T10:00:00+00:00';

    public function testRejectsTheCallAboveTheLimit(): void
    {
        $limiter = new SlidingWindowRateLimiter(3, 10, new SystemClock(new MockClock(self::START)));
        $tenant = TenantId::fromString('tenant-a');
        self::fill($limiter, $tenant, 3);

        $this->expectExceptionObject(RateLimitExceeded::forOperation(Operation::VerifyEmployer, 10));

        $limiter->acquire($tenant, Operation::VerifyEmployer);
    }

    public function testRetryDelayIsTheTimeUntilTheOldestCallLeavesTheWindow(): void
    {
        $clock = new MockClock(self::START);
        $limiter = new SlidingWindowRateLimiter(3, 60, new SystemClock($clock));
        $tenant = TenantId::fromString('tenant-a');
        $limiter->acquire($tenant, Operation::VerifyEmployer);
        $clock->modify('+10 seconds');
        $limiter->acquire($tenant, Operation::VerifyEmployer);
        $limiter->acquire($tenant, Operation::VerifyEmployer);

        static::assertSame(50, self::retryAfterOrNull($limiter, $tenant));
    }

    public function testRetryDelayNeverExceedsTheWindowWhenTheClockGoesBackwards(): void
    {
        $clock = new MockClock(self::START);
        $limiter = new SlidingWindowRateLimiter(1, 60, new SystemClock($clock));
        $tenant = TenantId::fromString('tenant-a');
        $clock->modify('+30 seconds');
        $limiter->acquire($tenant, Operation::VerifyEmployer);
        $clock->modify('-20 seconds');

        static::assertSame(60, self::retryAfterOrNull($limiter, $tenant));
    }

    public function testRetryDelayFollowsTheOldestCallEvenWhenTheClockWentBackwards(): void
    {
        $clock = new MockClock(self::START);
        $limiter = new SlidingWindowRateLimiter(3, 60, new SystemClock($clock));
        $tenant = TenantId::fromString('tenant-a');
        $clock->modify('+30 seconds');
        $limiter->acquire($tenant, Operation::VerifyEmployer);
        $clock->modify('-25 seconds');
        $limiter->acquire($tenant, Operation::VerifyEmployer);
        $clock->modify('+15 seconds');
        $limiter->acquire($tenant, Operation::VerifyEmployer);

        static::assertSame(45, self::retryAfterOrNull($limiter, $tenant));
    }

    public function testCallsLeaveTheWindowExactlyWindowSecondsLater(): void
    {
        $clock = new MockClock(self::START);
        $limiter = new SlidingWindowRateLimiter(2, 10, new SystemClock($clock));
        $tenant = TenantId::fromString('tenant-a');
        self::fill($limiter, $tenant, 2);

        $clock->modify('+9 seconds');
        static::assertSame(1, self::retryAfterOrNull($limiter, $tenant));

        $clock->modify('+1 second');
        static::assertNull(self::retryAfterOrNull($limiter, $tenant));
    }

    public function testRejectedCallsAreNotCounted(): void
    {
        $clock = new MockClock(self::START);
        $limiter = new SlidingWindowRateLimiter(1, 10, new SystemClock($clock));
        $tenant = TenantId::fromString('tenant-a');
        $limiter->acquire($tenant, Operation::VerifyEmployer);
        $clock->modify('+5 seconds');
        static::assertSame(5, self::retryAfterOrNull($limiter, $tenant));

        $clock->modify('+5 seconds');

        static::assertNull(self::retryAfterOrNull($limiter, $tenant));
    }

    private static function fill(SlidingWindowRateLimiter $limiter, TenantId $tenant, int $calls): void
    {
        for ($i = 0; $i < $calls; ++$i) {
            $limiter->acquire($tenant, Operation::VerifyEmployer);
        }
    }

    /**
     * Returns the retry delay when the call is rejected, null when it is allowed.
     */
    private static function retryAfterOrNull(SlidingWindowRateLimiter $limiter, TenantId $tenant): ?int
    {
        try {
            $limiter->acquire($tenant, Operation::VerifyEmployer);
        } catch (RateLimitExceeded $exception) {
            return $exception->retryAfterSeconds();
        }

        return null;
    }
}
