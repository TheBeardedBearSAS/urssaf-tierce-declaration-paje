<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command;

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\EmployeeAssociationView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\EmployeeAssociator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\AdministrativeIdentity;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByAdministrativeData;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\Violation;

/**
 * Method 023, synchronous. With an {@see EmployeeByAdministrativeData} identification, the identity of that
 * identification is never sent (api-reference §5): the creation must therefore hold a non-empty identity, otherwise the
 * request would carry no criterion at all, and the command is refused before any call with the violation
 * "employee_association.identity_required" (path "creation.identity", ADR 0009).
 *
 * Every failure of the call propagates. 023 is not resent after an {@see OutcomeUnknown} unless declared safe (ADR 0003):
 * the host checks, then calls this use case again (an already associated employee answers the non-blocking
 * `ER_API_023_NB_0000`).
 */
final readonly class AssociateEmployeeHandler
{
    public const IDENTITY_REQUIRED = 'employee_association.identity_required';

    public function __construct(private EmployeeAssociator $associator) {}

    /**
     * @throws ValidationFailed
     * @throws OutcomeUnknown
     * @throws UrssafRequestRejected
     * @throws UrssafAuthenticationFailed
     * @throws UrssafUnavailable
     * @throws RateLimitExceeded
     */
    public function __invoke(AssociateEmployee $command): EmployeeAssociationView
    {
        $identity = $command->creation->identity();
        if ($command->employee instanceof EmployeeByAdministrativeData && (!$identity instanceof AdministrativeIdentity || $identity->isEmpty())) {
            throw ValidationFailed::withResult(ValidationResult::of(Violation::error(
                'creation.identity',
                self::IDENTITY_REQUIRED,
                'An employee identified by administrative data needs a non-empty identity in its creation data.',
            )));
        }

        return EmployeeAssociationView::fromAssociation(
            $this->associator->associateEmployee($command->tenantId, $command->employer, $command->employee, $command->creation),
        );
    }
}
