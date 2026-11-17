<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command;

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\EmployerSelector;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Records the cancellation of the mandate of a tracked employer without any URSSAF call: the counterpart of
 * ImportMandate, for a mandate cancelled outside the package or at URSSAF by a CancelMandate whose local recording failed
 * ("mandate.recording_failed", ADR 0006).
 */
final readonly class RecordMandateCancellation
{
    public function __construct(
        public TenantId $tenantId,
        public EmployerSelector $employer,
    ) {}
}
