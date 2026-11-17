<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\ChildminderDeclaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\HomeCareDeclaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\UrssafException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Operations 050 and 051: declares a salary (api-reference §2). Never resent automatically (ADR 0003): there is no way to
 * look a declaration up and a repeated declaration is rejected ({@see RejectionKind::DuplicateDeclaration}).
 */
interface SalaryDeclarer
{
    /**
     * Declares a childminder salary (050).
     *
     * @throws OutcomeUnknown             a transport error, a 5xx or an unreadable answer may mean URSSAF processed the
     *                                    call: never resent (ADR 0003)
     * @throws UrssafRequestRejected      HTTP 400, classified ({@see RejectionKind})
     * @throws UrssafAuthenticationFailed
     * @throws UrssafUnavailable          retryable, only when the call provably did not reach URSSAF (429, token failure)
     * @throws RateLimitExceeded          the local limiter refused the call
     *
     * Any other {@see UrssafException} means URSSAF answered something the contract does not describe.
     */
    public function declareChildminder(
        TenantId $tenantId,
        #[SensitiveParameter]
        EmployerReference $employer,
        #[SensitiveParameter]
        ChildminderDeclaration $declaration,
    ): DeclarationResult;

    /**
     * Declares a home care salary (051).
     *
     * @throws OutcomeUnknown             a transport error, a 5xx or an unreadable answer may mean URSSAF processed the
     *                                    call: never resent (ADR 0003)
     * @throws UrssafRequestRejected      HTTP 400, classified ({@see RejectionKind})
     * @throws UrssafAuthenticationFailed
     * @throws UrssafUnavailable          retryable, only when the call provably did not reach URSSAF (429, token failure)
     * @throws RateLimitExceeded          the local limiter refused the call
     *
     * Any other {@see UrssafException} means URSSAF answered something the contract does not describe.
     */
    public function declareHomeCare(
        TenantId $tenantId,
        #[SensitiveParameter]
        EmployerReference $employer,
        #[SensitiveParameter]
        HomeCareDeclaration $declaration,
    ): DeclarationResult;
}
