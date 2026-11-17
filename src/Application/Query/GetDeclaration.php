<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query;

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\DeclarationSelector;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Reads one declaration record of a tenant, by record id or external reference.
 */
final readonly class GetDeclaration
{
    public function __construct(public TenantId $tenantId, public DeclarationSelector $declaration) {}
}
