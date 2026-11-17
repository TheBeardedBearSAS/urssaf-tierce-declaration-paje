<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\RateLimiter;

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\RateLimiter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Non-blocking in-memory limiter of the global URSSAF quota (ADR 0011): at most $limit calls per tenant, all operations
 * together, in any sliding window of $windowSeconds (URSSAF publishes no value, so there is no default, api-reference §1.1). Per process.
 * Token calls are not counted (the gateway never acquires for them). Call instants are kept at second precision; when the
 * limit is reached the retry delay is the time left until the oldest call leaves the window.
 */
final class SlidingWindowRateLimiter implements RateLimiter
{
    /** @var array<string, array<int, int>> call instants (UNIX timestamps) per tenant identifier */
    private array $calls = [];

    /**
     * @throws InvalidValue "rate_limit.not_positive" / "rate_limit_window.not_positive"
     */
    public function __construct(
        private readonly int $limit,
        private readonly int $windowSeconds,
        private readonly Clock $clock,
    ) {
        if ($limit < 1) {
            throw InvalidValue::notPositive('rate_limit');
        }

        if ($windowSeconds < 1) {
            throw InvalidValue::notPositive('rate_limit_window');
        }
    }

    /**
     * @throws RateLimitExceeded
     */
    public function acquire(TenantId $tenantId, Operation $operation): void
    {
        $now = $this->clock->now()->getTimestamp();
        $windowStart = $now - $this->windowSeconds;
        $recent = array_filter(
            $this->calls[$tenantId->value()] ?? [],
            static fn(int $instant): bool => $instant > $windowStart,
        );

        if (\count($recent) >= $this->limit) {
            throw RateLimitExceeded::forOperation($operation, min([$now, ...$recent]) + $this->windowSeconds - $now);
        }

        $recent[] = $now;
        $this->calls[$tenantId->value()] = $recent;
    }
}
