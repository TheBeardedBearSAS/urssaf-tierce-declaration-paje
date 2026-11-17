<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeCreation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeIdentification;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Associates an employee to an employer, creating the employee when URSSAF does not know it (method 023). No local
 * aggregate and no tracked employer needed (ADR 0006). With an administrative-data identification the creation must carry a
 * non-empty identity: that identity is the only criterion sent (ADR 0009, amendment of lot 1).
 */
final readonly class AssociateEmployee
{
    public function __construct(
        public TenantId $tenantId,
        #[SensitiveParameter]
        public EmployerReference $employer,
        #[SensitiveParameter]
        public EmployeeIdentification $employee,
        #[SensitiveParameter]
        public EmployeeCreation $creation,
    ) {}
}
