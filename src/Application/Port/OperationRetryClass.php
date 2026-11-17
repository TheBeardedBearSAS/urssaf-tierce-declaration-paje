<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port;

/**
 * How an operation may be resent after a failure whose outcome is uncertain (ADR 0003).
 */
enum OperationRetryClass: string
{
    /** Reads and pre-declarations: nothing is recorded, a transport error or a 5xx is a retryable failure. */
    case Safe = 'safe';

    /** Declarations (050, 051): never resent automatically, a transport error or a 5xx is an unknown outcome. */
    case NeverResent = 'never_resent';

    /**
     * Writes whose idempotency the specification does not state (023, 030, 031): never resent by default, the
     * host may declare them safe once the sandbox has shown it (ADR 0003, `ApiConfiguration::retrySafeWrites()`).
     */
    case UnspecifiedWrite = 'unspecified_write';
}
