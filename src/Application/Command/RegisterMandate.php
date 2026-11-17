<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command;

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\EmployerSelector;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Notifies URSSAF that the platform holds the mandate of a tracked employer (method 030, api-reference §2.11).
 */
final readonly class RegisterMandate
{
    public function __construct(
        public TenantId $tenantId,
        public EmployerSelector $employer,
    ) {}
}
