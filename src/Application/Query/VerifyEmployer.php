<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Reads the state of the online account of an employer (method 020). When the employer is tracked by the tenant, the
 * answer is also stored as the snapshot of its account (ADR 0006).
 */
final readonly class VerifyEmployer
{
    public function __construct(
        public TenantId $tenantId,
        #[SensitiveParameter]
        public EmployerReference $employer,
    ) {}
}
