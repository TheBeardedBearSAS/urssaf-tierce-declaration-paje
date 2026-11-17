<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory;

use Override;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Process-local {@see MandateRepository} for tests and demos.
 *
 * Stores snapshots like {@see InMemoryEmployerAccountRepository} (copy on save and on every read, pending events never
 * persisted nor consumed).
 *
 * Optimistic concurrency: see {@see VersionTokens}. Saving a copy another writer changed since it was read is refused
 * (a never-read instance is refused too when the identifier is already stored).
 */
final class InMemoryMandateRepository implements MandateRepository
{
    /** @var array<string, Mandate> stored snapshots by mandate id, in insertion order */
    private array $mandates = [];

    private readonly VersionTokens $versions;

    public function __construct()
    {
        $this->versions = new VersionTokens(
            MandateRepository::CONCURRENT_MODIFICATION,
            'The mandate was changed by another writer since it was read.',
        );
    }

    /**
     * Copies of every stored mandate (monitoring, tests).
     *
     * @return list<Mandate>
     */
    public function all(): array
    {
        return Selection::of($this->mandates)->copies($this->adopt(...));
    }

    /**
     * @throws InvariantViolation "mandate.concurrent_modification" (saved by another writer, or erased, since this
     *                            instance was read) or "mandate.duplicate_active"
     */
    #[Override]
    public function save(Mandate $mandate): void
    {
        $id = $mandate->id()->value();
        $this->versions->assertCurrent($mandate, $id);
        $this->assertSingleActive($mandate);
        $snapshot = clone $mandate;
        $snapshot->releaseEvents();
        $this->mandates[$id] = $snapshot;
        $this->versions->saved($mandate, $id);
    }

    #[Override]
    public function get(MandateId $id): Mandate
    {
        $stored = $this->mandates[$id->value()] ?? throw RecordNotFound::withIdentifier('mandate', $id->value());

        return $this->versions->track(clone $stored, $id->value());
    }

    #[Override]
    public function findActive(TenantId $tenantId, PajeEmployerNumber $employerNumber): ?Mandate
    {
        return Selection::of($this->mandates)
            ->where(
                static fn(Mandate $mandate): bool => $mandate->isActive()
                    && $mandate->tenantId()->equals($tenantId)
                    && $mandate->employerNumber()->equals($employerNumber),
            )
            ->copies($this->adopt(...))[0] ?? null;
    }

    #[Override]
    public function findByEmployer(TenantId $tenantId, PajeEmployerNumber $employerNumber): array
    {
        return Selection::of($this->mandates)
            ->where(
                static fn(Mandate $mandate): bool => $mandate->tenantId()->equals($tenantId)
                    && $mandate->employerNumber()->equals($employerNumber),
            )
            ->sortedBy(
                static fn(Mandate $a, Mandate $b): int => Selection::compareInstants($a->registeredAt(), $b->registeredAt()),
            )
            ->copies($this->adopt(...));
    }

    #[Override]
    public function delete(MandateId $id): void
    {
        unset($this->mandates[$id->value()]);
        $this->versions->forget($id->value());
    }

    private function adopt(Mandate $copy): Mandate
    {
        return $this->versions->track($copy, $copy->id()->value());
    }

    private function assertSingleActive(Mandate $mandate): void
    {
        if (!$mandate->isActive()) {
            return;
        }
        foreach ($this->mandates as $stored) {
            if (
                !$stored->id()->equals($mandate->id())
                && $stored->isActive()
                && $stored->tenantId()->equals($mandate->tenantId())
                && $stored->employerNumber()->equals($mandate->employerNumber())
            ) {
                throw InvariantViolation::because(
                    'mandate.duplicate_active',
                    'An active mandate already exists for this employer.',
                );
            }
        }
    }
}
