<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Declaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Pre-declares a salary (methods 045 / 046 by declaration variant): validates it with URSSAF and returns the computed amounts
 * without recording anything, locally or at URSSAF (ADR 0008).
 *
 * "skipMandateCheck" opts out of the local mandate guard for this query (ADR 0009).
 */
final readonly class PreDeclareSalary
{
    public function __construct(
        public TenantId $tenantId,
        #[SensitiveParameter]
        public EmployerReference $employer,
        #[SensitiveParameter]
        public Declaration $declaration,
        public bool $skipMandateCheck = false,
    ) {}
}
