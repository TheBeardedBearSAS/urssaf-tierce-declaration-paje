<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Counts of the records by state, for every tenant that has records, or for one tenant (ADR 0003).
 */
final readonly class GetWorkflowOverview
{
    private function __construct(public ?TenantId $tenantId) {}

    public static function allTenants(): self
    {
        return new self(null);
    }

    public static function forTenant(TenantId $tenantId): self
    {
        return new self($tenantId);
    }
}
