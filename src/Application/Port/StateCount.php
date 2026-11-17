<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Number of records of one tenant in one state (employer account, mandate or declaration).
 */
final readonly class StateCount
{
    /**
     * @param string $state the value of an EmployerStage, a MandateState or a DeclarationState
     */
    public function __construct(public TenantId $tenantId, public string $state, public int $count) {}
}
