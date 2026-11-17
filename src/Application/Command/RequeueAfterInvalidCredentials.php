<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Moves the declarations refused for invalid credentials back to pending (once the tenant credentials are fixed).
 *
 * The limit is capped by the workflow policy (default page size when null).
 */
final readonly class RequeueAfterInvalidCredentials
{
    public function __construct(public TenantId $tenantId, public ?int $limit) {}
}
