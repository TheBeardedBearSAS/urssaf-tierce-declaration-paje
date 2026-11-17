<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\UrssafException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Operations 030 and 031: registers or cancels the mandate of the platform on an employer (api-reference §2). The
 * specification does not state their idempotency: an unknown outcome is not resent by default (ADR 0003).
 */
interface MandateRegistrar
{
    /**
     * Registers the mandate (030).
     *
     * @throws OutcomeUnknown             a transport error or a 5xx may have reached URSSAF: not resent unless the
     *                                    operation was declared safe (ADR 0003)
     * @throws UrssafRequestRejected      HTTP 400, classified ({@see RejectionKind})
     * @throws UrssafAuthenticationFailed
     * @throws UrssafUnavailable          retryable: 429, token failure (and, when declared safe, transport error or 5xx)
     * @throws RateLimitExceeded          the local limiter refused the call
     *
     * Any other {@see UrssafException} means URSSAF answered something the contract does not describe.
     */
    public function registerMandate(TenantId $tenantId, #[SensitiveParameter] EmployerReference $employer): void;

    /**
     * Cancels the mandate (031).
     *
     * @throws OutcomeUnknown             a transport error or a 5xx may have reached URSSAF: not resent unless the
     *                                    operation was declared safe (ADR 0003)
     * @throws UrssafRequestRejected      HTTP 400, classified ({@see RejectionKind})
     * @throws UrssafAuthenticationFailed
     * @throws UrssafUnavailable          retryable: 429, token failure (and, when declared safe, transport error or 5xx)
     * @throws RateLimitExceeded          the local limiter refused the call
     *
     * Any other {@see UrssafException} means URSSAF answered something the contract does not describe.
     */
    public function cancelMandate(TenantId $tenantId, #[SensitiveParameter] EmployerReference $employer): void;
}
