<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\DomainException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;

/**
 * Thrown when an employer cannot be tracked because its external reference or its Pajemploi number already belongs to
 * another employer account of the tenant (ADR 0006). The employer number never appears in the message or the parameters
 * (ADR 0007).
 *
 * Violation code: "employer_account.already_tracked".
 */
final class EmployerAlreadyTracked extends DomainException
{
    private const CODE = 'employer_account.already_tracked';

    public static function withExternalReference(ExternalReference $externalReference, EmployerAccountId $existing): self
    {
        return new self(
            'The external reference is already used by another employer account.',
            self::CODE,
            ['external_reference' => $externalReference->value(), 'employer_account_id' => $existing->value()],
        );
    }

    public static function withEmployerNumber(EmployerAccountId $existing): self
    {
        return new self(
            'The employer is already tracked by another employer account.',
            self::CODE,
            ['employer_account_id' => $existing->value()],
        );
    }
}
