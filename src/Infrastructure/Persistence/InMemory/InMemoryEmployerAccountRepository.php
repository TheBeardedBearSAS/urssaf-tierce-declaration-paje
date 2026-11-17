<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory;

use Override;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Process-local {@see EmployerAccountRepository} for tests and demos.
 *
 * Identity semantics: like a database, it stores a snapshot. {@see self::save()} copies the aggregate (without its
 * pending domain events, which are never persisted) and every read returns a new copy, so a change is only visible
 * once saved and the events pending on the caller's instance are never consumed.
 *
 * Insertion order is kept for ties.
 *
 * Optimistic concurrency: see {@see VersionTokens}. Saving a copy another writer changed since it was read is refused
 * (a never-read instance is refused too when the identifier is already stored).
 *
 * One method per query of the port plus the uniqueness and version helpers, hence the method count.
 *
 * @mago-expect lint:too-many-methods
 */
final class InMemoryEmployerAccountRepository implements EmployerAccountRepository
{
    /** @var array<string, EmployerAccount> stored snapshots by account id, in insertion order */
    private array $accounts = [];

    private readonly VersionTokens $versions;

    public function __construct()
    {
        $this->versions = new VersionTokens(
            EmployerAccountRepository::CONCURRENT_MODIFICATION,
            'The employer account was changed by another writer since it was read.',
        );
    }

    /**
     * Copies of every stored account (monitoring, tests).
     *
     * @return list<EmployerAccount>
     */
    public function all(): array
    {
        return Selection::of($this->accounts)->copies($this->adopt(...));
    }

    /**
     * @throws InvariantViolation "employer_account.concurrent_modification" (saved by another writer, or erased, since
     *                            this instance was read), "employer_account.duplicate_employer_number" or
     *                            "employer_account.duplicate_external_reference"
     */
    #[Override]
    public function save(EmployerAccount $account): void
    {
        $id = $account->id()->value();
        $this->versions->assertCurrent($account, $id);
        $this->assertUnique($account);
        $snapshot = clone $account;
        $snapshot->releaseEvents();
        $this->accounts[$id] = $snapshot;
        $this->versions->saved($account, $id);
    }

    #[Override]
    public function get(EmployerAccountId $id): EmployerAccount
    {
        $stored = $this->accounts[$id->value()] ?? throw RecordNotFound::withIdentifier('employer_account', $id->value());

        return $this->versions->track(clone $stored, $id->value());
    }

    #[Override]
    public function findByEmployerNumber(TenantId $tenantId, PajeEmployerNumber $employerNumber): ?EmployerAccount
    {
        return Selection::of($this->accounts)
            ->where(
                static fn(EmployerAccount $account): bool => $account->tenantId()->equals($tenantId)
                    && $account->employerNumber()->equals($employerNumber),
            )
            ->copies($this->adopt(...))[0] ?? null;
    }

    #[Override]
    public function findByExternalReference(TenantId $tenantId, ExternalReference $externalReference): ?EmployerAccount
    {
        return Selection::of($this->accounts)
            ->where(
                static fn(EmployerAccount $account): bool => $account->tenantId()->equals($tenantId)
                    && $account->externalReference()->equals($externalReference),
            )
            ->copies($this->adopt(...))[0] ?? null;
    }

    #[Override]
    public function findLeastRecentlyChecked(TenantId $tenantId, int $limit): array
    {
        return Selection::of($this->accounts)
            ->where(static fn(EmployerAccount $account): bool => $account->tenantId()->equals($tenantId))
            ->sortedBy(
                static fn(EmployerAccount $a, EmployerAccount $b): int => Selection::compareInstants(
                    $a->lastCheckedAt(),
                    $b->lastCheckedAt(),
                ),
            )
            ->limit($limit)
            ->copies($this->adopt(...));
    }

    #[Override]
    public function tenants(): array
    {
        return array_map(
            static fn(EmployerAccount $account): TenantId => $account->tenantId(),
            Selection::of($this->accounts)
                ->distinctBy(static fn(EmployerAccount $account): string => $account->tenantId()->value())
                ->sortedBy(
                    static fn(EmployerAccount $a, EmployerAccount $b): int => strcmp(
                        $a->tenantId()->value(),
                        $b->tenantId()->value(),
                    ),
                )
                ->all(),
        );
    }

    #[Override]
    public function delete(EmployerAccountId $id): void
    {
        unset($this->accounts[$id->value()]);
        $this->versions->forget($id->value());
    }

    private function adopt(EmployerAccount $copy): EmployerAccount
    {
        return $this->versions->track($copy, $copy->id()->value());
    }

    private function assertUnique(EmployerAccount $account): void
    {
        $others = Selection::of($this->accounts)->where(
            static fn(EmployerAccount $stored): bool => !$stored->id()->equals($account->id())
                && $stored->tenantId()->equals($account->tenantId()),
        )->all();
        foreach ($others as $stored) {
            if ($stored->employerNumber()->equals($account->employerNumber())) {
                throw InvariantViolation::because(
                    'employer_account.duplicate_employer_number',
                    'An employer account already exists for this employer number.',
                );
            }
        }
        foreach ($others as $stored) {
            if ($stored->externalReference()->equals($account->externalReference())) {
                throw InvariantViolation::because(
                    'employer_account.duplicate_external_reference',
                    'An employer account already exists for this external reference.',
                    ['external_reference' => $account->externalReference()->value()],
                );
            }
        }
    }
}
