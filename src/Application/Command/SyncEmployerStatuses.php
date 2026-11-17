<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Reads the 020 snapshot of the least recently checked employers of a tenant, never checked first.
 *
 * The limit is capped by the workflow policy (default page size when null).
 */
final readonly class SyncEmployerStatuses
{
    public function __construct(
        public TenantId $tenantId,
        public ?int $limit,
    ) {}
}
