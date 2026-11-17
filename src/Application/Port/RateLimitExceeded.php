<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port;

use RuntimeException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RetryableException;

/**
 * Thrown by a non-blocking {@see RateLimiter} when no call slot is available. Proves that the call did not reach URSSAF:
 * safe to retry for every operation (ADR 0003, ADR 0011).
 */
final class RateLimitExceeded extends RuntimeException implements RetryableException
{
    private function __construct(
        string $message,
        private readonly Operation $operation,
        private readonly int $retryAfterSeconds,
    ) {
        parent::__construct($message);
    }

    public static function forOperation(Operation $operation, int $retryAfterSeconds): self
    {
        $retryAfterSeconds = max(0, $retryAfterSeconds);

        return new self(
            \sprintf(
                'Local rate limit reached for URSSAF method %s; retry in %d second(s).',
                $operation->value,
                $retryAfterSeconds,
            ),
            $operation,
            $retryAfterSeconds,
        );
    }

    public function operation(): Operation
    {
        return $this->operation;
    }

    public function retryAfterSeconds(): int
    {
        return $this->retryAfterSeconds;
    }
}
