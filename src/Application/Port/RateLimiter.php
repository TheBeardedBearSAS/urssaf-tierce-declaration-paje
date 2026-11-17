<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Client-side throttling of the URSSAF quota (ADR 0011): URSSAF publishes no value, so the default implementation never
 * throttles and the quota applies as HTTP 429. Token calls are not counted.
 *
 * The operation is informative (logs, messages): implementations must not keep per-operation quotas.
 */
interface RateLimiter
{
    /**
     * Reserves one call slot, blocking until one is available or throwing when the implementation is non-blocking.
     *
     * @throws RateLimitExceeded
     */
    public function acquire(TenantId $tenantId, Operation $operation): void;
}
