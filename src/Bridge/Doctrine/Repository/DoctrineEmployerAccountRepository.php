<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\DoctrineTransactionManager;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\EmployerAccountIdType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\ExternalReferenceType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\PajeEmployerNumberType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\TenantIdType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Doctrine ORM implementation of {@see EmployerAccountRepository} with the semantics of the in-memory one: uniqueness
 * violations are reported as {@see InvariantViolation} with the same codes (the employer number, which is
 * credential-like, is never in the parameters), lists are ordered as the port says (never checked accounts first, then
 * oldest check, creation and id).
 *
 * Unit of work: {@see self::save()} persists and flushes immediately (a flush writes every dirty managed entity), so a
 * save inside a {@see DoctrineTransactionManager} transaction is durable only when that transaction commits. Reads
 * return the managed instance, so unlike the in-memory repository a change is visible to every holder of the instance
 * before it is saved.
 *
 * Concurrency (see {@see AggregateStore}): a copy that another writer changed or erased since it was read, and an
 * instance that is not managed while its identifier is stored, are refused with
 * "employer_account.concurrent_modification", before any flush.
 *
 * Implements the repository port: one method per query of the port, plus the uniqueness helpers.
 */
final readonly class DoctrineEmployerAccountRepository implements EmployerAccountRepository
{
    private const ENTITY = EmployerAccount::class;

    /** @var AggregateStore<EmployerAccount> */
    private AggregateStore $store;

    public function __construct(private EntityManagerInterface $entityManager)
    {
        $this->store = new AggregateStore(
            $entityManager,
            self::ENTITY,
            EmployerAccountIdType::NAME,
            self::CONCURRENT_MODIFICATION,
            'The employer account was changed by another writer since it was read: read it again.',
        );
    }

    /**
     * @throws InvariantViolation "employer_account.concurrent_modification", "employer_account.duplicate_employer_number"
     *                            or "employer_account.duplicate_external_reference"
     */
    #[Override]
    public function save(EmployerAccount $account): void
    {
        $this->store->save(
            $account,
            $account->id(),
            $this->assertUnique(...),
            static function (): void {},
        );
    }

    #[Override]
    public function get(EmployerAccountId $id): EmployerAccount
    {
        return $this->store->find($id) ?? throw RecordNotFound::withIdentifier(
            'employer_account',
            $id->value(),
        );
    }

    #[Override]
    public function findByEmployerNumber(TenantId $tenantId, PajeEmployerNumber $employerNumber): ?EmployerAccount
    {
        return Hydration::first(
            $this->entityManager
                ->createQuery(
                    'SELECT a FROM ' . self::ENTITY . ' a WHERE a.tenantId = :tenant AND a.employer.number = :number',
                )
                ->setParameter('tenant', $tenantId, TenantIdType::NAME)
                ->setParameter('number', $employerNumber, PajeEmployerNumberType::NAME)
                ->toIterable(),
            self::ENTITY,
        );
    }

    #[Override]
    public function findByExternalReference(TenantId $tenantId, ExternalReference $externalReference): ?EmployerAccount
    {
        return Hydration::first(
            $this->entityManager
                ->createQuery(
                    'SELECT a FROM ' . self::ENTITY . ' a WHERE a.tenantId = :tenant AND a.externalReference = :reference',
                )
                ->setParameter('tenant', $tenantId, TenantIdType::NAME)
                ->setParameter('reference', $externalReference, ExternalReferenceType::NAME)
                ->toIterable(),
            self::ENTITY,
        );
    }

    #[Override]
    public function findLeastRecentlyChecked(TenantId $tenantId, int $limit): array
    {
        return Hydration::all(
            $this->entityManager
                ->createQuery(
                    'SELECT a, CASE WHEN a.tracking.lastCheckedAt IS NULL THEN 0 ELSE 1 END AS HIDDEN checkedRank FROM '
                    . self::ENTITY
                    . ' a WHERE a.tenantId = :tenant'
                    . ' ORDER BY checkedRank ASC, a.tracking.lastCheckedAt ASC, a.createdAt ASC, a.id ASC',
                )
                ->setParameter('tenant', $tenantId, TenantIdType::NAME)
                ->setMaxResults($limit)
                ->toIterable(),
            self::ENTITY,
        );
    }

    #[Override]
    public function tenants(): array
    {
        /** @var list<string> $tenants */
        $tenants = $this->entityManager
            ->createQuery('SELECT DISTINCT a.tenantId FROM ' . self::ENTITY . ' a ORDER BY a.tenantId ASC')
            ->getSingleColumnResult();

        return array_map(TenantId::fromString(...), $tenants);
    }

    #[Override]
    public function delete(EmployerAccountId $id): void
    {
        $this->store->discard($id);
    }

    /**
     * @throws InvariantViolation
     */
    private function assertUnique(EmployerAccount $account): void
    {
        if ($this->exists($account, 'a.employer.number = :value', $account->employerNumber(), PajeEmployerNumberType::NAME)) {
            // The employer number is credential-like (ADR 0007): never in an exception.
            throw InvariantViolation::because(
                'employer_account.duplicate_employer_number',
                'An employer account already exists for this employer number.',
            );
        }
        if ($this->exists($account, 'a.externalReference = :value', $account->externalReference(), ExternalReferenceType::NAME)) {
            throw InvariantViolation::because(
                'employer_account.duplicate_external_reference',
                'An employer account already exists for this external reference.',
                ['external_reference' => $account->externalReference()->value()],
            );
        }
    }

    /**
     * @param non-empty-string $type DBAL type of the value
     */
    private function exists(EmployerAccount $account, string $condition, object $value, string $type): bool
    {
        return $this->entityManager
            ->createQuery(
                'SELECT a.id FROM ' . self::ENTITY . ' a WHERE a.tenantId = :tenant AND a.id <> :id AND ' . $condition,
            )
            ->setParameter('tenant', $account->tenantId(), TenantIdType::NAME)
            ->setParameter('id', $account->id(), EmployerAccountIdType::NAME)
            ->setParameter('value', $value, $type)
            ->getScalarResult() !== [];
    }
}
