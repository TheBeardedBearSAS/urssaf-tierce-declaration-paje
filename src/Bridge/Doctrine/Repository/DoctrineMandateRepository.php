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
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Entity\ActiveMandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\MandateIdType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\PajeEmployerNumberType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\TenantIdType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Doctrine ORM implementation of {@see MandateRepository} with the semantics of the in-memory one: at most one active
 * (non-cancelled) mandate per employer, reported as {@see InvariantViolation} "mandate.duplicate_active" (without the
 * employer number, which is credential-like, ADR 0007) and enforced by the database through the key table of
 * {@see ActiveMandate} (primary key (tenant, employer number)), rewritten with DBAL in the transaction of every save.
 *
 * The queries select the active mandates as the ones not in the state "cancelled" ({@see MandateState::isActive()}; a test
 * fails if a state is ever added).
 *
 * Unit of work: {@see self::save()} persists and flushes immediately (a flush writes every dirty managed entity), so a
 * save inside a {@see DoctrineTransactionManager} transaction is durable only when that transaction commits. Reads
 * return the managed instance, so unlike the in-memory repository a change is visible to every holder of the instance
 * before it is saved.
 *
 * Concurrency (see {@see AggregateStore}): a copy that another writer changed or erased since it was read, and an
 * instance that is not managed while its identifier is stored, are refused with "mandate.concurrent_modification",
 * before any flush.
 *
 * Implements the repository port: one method per query of the port, plus the uniqueness, key row and helpers.
 */
final readonly class DoctrineMandateRepository implements MandateRepository
{
    private const ENTITY = Mandate::class;

    private const BY_REGISTRATION = ' ORDER BY m.timeline.registeredAt ASC, m.id ASC';

    /** @var AggregateStore<Mandate> */
    private AggregateStore $store;

    public function __construct(private EntityManagerInterface $entityManager)
    {
        $this->store = new AggregateStore(
            $entityManager,
            self::ENTITY,
            MandateIdType::NAME,
            self::CONCURRENT_MODIFICATION,
            'The mandate was changed by another writer since it was read: read it again.',
        );
    }

    /**
     * @throws InvariantViolation "mandate.concurrent_modification" or "mandate.duplicate_active"
     */
    #[Override]
    public function save(Mandate $mandate): void
    {
        $this->store->save(
            $mandate,
            $mandate->id(),
            $this->assertSingleActive(...),
            $this->replaceKey(...),
        );
    }

    #[Override]
    public function get(MandateId $id): Mandate
    {
        return $this->store->find($id) ?? throw RecordNotFound::withIdentifier(
            'mandate',
            $id->value(),
        );
    }

    #[Override]
    public function findActive(TenantId $tenantId, PajeEmployerNumber $employerNumber): ?Mandate
    {
        return Hydration::first(
            $this->entityManager
                ->createQuery(
                    'SELECT m FROM ' . self::ENTITY . ' m WHERE m.tenantId = :tenant AND m.employer.number = :number'
                    . ' AND m.state <> :cancelled' . self::BY_REGISTRATION,
                )
                ->setParameter('tenant', $tenantId, TenantIdType::NAME)
                ->setParameter('number', $employerNumber, PajeEmployerNumberType::NAME)
                ->setParameter('cancelled', MandateState::Cancelled)
                ->toIterable(),
            self::ENTITY,
        );
    }

    #[Override]
    public function findByEmployer(TenantId $tenantId, PajeEmployerNumber $employerNumber): array
    {
        return Hydration::all(
            $this->entityManager
                ->createQuery(
                    'SELECT m FROM ' . self::ENTITY . ' m WHERE m.tenantId = :tenant AND m.employer.number = :number'
                    . self::BY_REGISTRATION,
                )
                ->setParameter('tenant', $tenantId, TenantIdType::NAME)
                ->setParameter('number', $employerNumber, PajeEmployerNumberType::NAME)
                ->toIterable(),
            self::ENTITY,
        );
    }

    /**
     * GDPR erasure: the mandate and its key row go in one transaction (nothing happens for an unknown mandate), so that
     * the employer can hold a new active mandate afterwards.
     */
    #[Override]
    public function delete(MandateId $id): void
    {
        $this->store->connection()->transactional(function () use ($id): void {
            $this->store->discard($id);
            $this->store->connection()->delete($this->keyTable(), ['mandate_id' => $id], ActiveMandate::TYPES);
        });
    }

    private function keyTable(): string
    {
        return $this->entityManager->getClassMetadata(ActiveMandate::class)->getTableName();
    }

    /**
     * Rewrites the key row of the mandate (delete and insert: no existence check, no driver-specific "affected rows"
     * semantics). A concurrent second active mandate of the employer fails on the primary key.
     */
    private function replaceKey(Mandate $mandate): void
    {
        $connection = $this->store->connection();
        $connection->delete($this->keyTable(), ['mandate_id' => $mandate->id()], ActiveMandate::TYPES);
        if (!$mandate->isActive()) {
            return;
        }
        $connection->insert($this->keyTable(), ActiveMandate::of($mandate)->row(), ActiveMandate::TYPES);
    }

    /**
     * @throws InvariantViolation "mandate.duplicate_active"
     */
    private function assertSingleActive(Mandate $mandate): void
    {
        if (!$mandate->isActive()) {
            return;
        }
        $others = $this->entityManager
            ->createQuery(
                'SELECT m.id FROM ' . self::ENTITY . ' m WHERE m.tenantId = :tenant AND m.employer.number = :number'
                . ' AND m.id <> :id AND m.state <> :cancelled',
            )
            ->setParameter('tenant', $mandate->tenantId(), TenantIdType::NAME)
            ->setParameter('number', $mandate->employerNumber(), PajeEmployerNumberType::NAME)
            ->setParameter('id', $mandate->id(), MandateIdType::NAME)
            ->setParameter('cancelled', MandateState::Cancelled)
            ->getScalarResult();
        if ($others !== []) {
            // The employer number is credential-like (ADR 0007): never in an exception.
            throw InvariantViolation::because(
                'mandate.duplicate_active',
                'An active mandate already exists for this employer.',
            );
        }
    }
}
