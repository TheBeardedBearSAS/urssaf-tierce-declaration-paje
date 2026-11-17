<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeAssociation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeCreation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeIdentification;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\UrssafException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Operation 023: associates an employee to an employer, creating the employee when needed (api-reference §2). The
 * specification does not state its idempotency: an unknown outcome is not resent by default (ADR 0003).
 */
interface EmployeeAssociator
{
    /**
     * Associates the employee. An already associated employee is answered with a non-blocking message.
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
    public function associateEmployee(
        TenantId $tenantId,
        #[SensitiveParameter]
        EmployerReference $employer,
        #[SensitiveParameter]
        EmployeeIdentification $employee,
        #[SensitiveParameter]
        EmployeeCreation $creation,
    ): EmployeeAssociation;
}
