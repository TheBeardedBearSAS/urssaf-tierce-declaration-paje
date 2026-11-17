<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception;

use RuntimeException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RetryableException;

/**
 * Thrown by DeclareSalary after a temporary failure that provably did not reach URSSAF (quota exceeded, token endpoint
 * unavailable, local rate limit, a failed pre-declaration): the record was moved back to pending and the declaration must be
 * retried later. The original failure (with its retry-after delay when known) is the previous exception.
 */
final class RetryableUrssafFailure extends RuntimeException implements RetryableException
{
    public static function causedBy(RetryableException $previous): self
    {
        return new self('URSSAF is temporarily unavailable: the declaration must be retried later.', 0, $previous);
    }

    /**
     * Seconds to wait before retrying, when the original failure states it.
     */
    public function retryAfterSeconds(): ?int
    {
        $previous = $this->getPrevious();

        return $previous instanceof UrssafUnavailable || $previous instanceof RateLimitExceeded
            ? $previous->retryAfterSeconds()
            : null;
    }
}
