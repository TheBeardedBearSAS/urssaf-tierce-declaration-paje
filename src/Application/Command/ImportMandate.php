<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command;

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\EmployerSelector;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Records the mandate of a tracked employer without any URSSAF call, for a mandate registered outside the package or
 * checked by an operator after an unknown outcome (ADR 0003, ADR 0006).
 */
final readonly class ImportMandate
{
    public function __construct(
        public TenantId $tenantId,
        public EmployerSelector $employer,
    ) {}
}
