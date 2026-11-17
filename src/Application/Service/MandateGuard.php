<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service;

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception\MandateRequired;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Local guard of the declaration operations (045, 046, 050, 051; ADR 0009): refuses them before any URSSAF call unless the
 * employer has a non-cancelled local mandate. Off by default ("requireLocalMandate" of the policy) and skipped by the
 * command opt-out. Method 020 says nothing about the mandate, so the guard relies on the local mandate only: an employer
 * mandated outside the package passes after ImportMandate.
 */
final readonly class MandateGuard
{
    public function __construct(private MandateRepository $mandates, private WorkflowPolicy $policy) {}

    /**
     * The flag is the opt-out of the command ("skipMandateCheck", ADR 0009), passed through as is.
     *
     * @mago-expect lint:no-boolean-flag-parameter
     *
     * @throws MandateRequired "mandate.required"
     */
    public function assertMandate(
        TenantId $tenantId,
        PajeEmployerNumber $employerNumber,
        Operation $operation,
        bool $skipMandateCheck,
    ): void {
        if ($skipMandateCheck || !$this->policy->requireLocalMandate()) {
            return;
        }
        if ($this->mandates->findActive($tenantId, $employerNumber) instanceof Mandate) {
            return;
        }

        throw MandateRequired::forOperation($operation);
    }
}
