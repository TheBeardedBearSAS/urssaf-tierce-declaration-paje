<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\EmployerSelector;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception\EmployerAlreadyTracked;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Loads the employer account designated by an {@see EmployerSelector}, within one tenant, and finds the account an
 * employer to track already has.
 */
final readonly class EmployerLocator
{
    private const RECORD_TYPE = 'employer_account';

    public function __construct(private EmployerAccountRepository $accounts) {}

    /**
     * @throws RecordNotFound "employer_account.not_found" (also for an account of another tenant)
     */
    public function locate(TenantId $tenantId, EmployerSelector $selector): EmployerAccount
    {
        $reference = $selector->reference;
        if ($reference instanceof ExternalReference) {
            return $this->accounts->findByExternalReference($tenantId, $reference)
                ?? throw RecordNotFound::withIdentifier(self::RECORD_TYPE, $reference->value());
        }

        if ($reference instanceof PajeEmployerNumber) {
            return $this->accounts->findByEmployerNumber($tenantId, $reference)
                ?? throw RecordNotFound::withIdentifier(self::RECORD_TYPE, '***');
        }

        $account = $this->accounts->get($reference);
        if (!$account->tenantId()->equals($tenantId)) {
            throw RecordNotFound::withIdentifier(self::RECORD_TYPE, $reference->value());
        }

        return $account;
    }

    /**
     * The account already tracking this employer under this external reference (the same employer: idempotent), or
     * null when neither the external reference nor the employer number is tracked yet.
     *
     * @throws EmployerAlreadyTracked the external reference belongs to another employer, or the employer number is
     *                                tracked under another external reference
     */
    public function findExisting(
        TenantId $tenantId,
        ExternalReference $externalReference,
        #[SensitiveParameter]
        EmployerReference $employer,
    ): ?EmployerAccount {
        $existing = $this->accounts->findByExternalReference($tenantId, $externalReference);
        if ($existing instanceof EmployerAccount) {
            if (!$existing->employer()->equals($employer)) {
                throw EmployerAlreadyTracked::withExternalReference($externalReference, $existing->id());
            }

            return $existing;
        }

        $tracked = $this->accounts->findByEmployerNumber($tenantId, $employer->number());
        if ($tracked instanceof EmployerAccount) {
            throw EmployerAlreadyTracked::withEmployerNumber($tracked->id());
        }

        return null;
    }
}
