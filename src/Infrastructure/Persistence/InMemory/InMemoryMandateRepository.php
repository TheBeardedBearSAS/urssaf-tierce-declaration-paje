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
 */
final class InMemoryMandateRepository implements MandateRepository
{
    /** @var array<string, Mandate> stored snapshots by mandate id, in insertion order */
    private array $mandates = [];

    /**
     * Copies of every stored mandate (monitoring, tests).
     *
     * @return list<Mandate>
     */
    public function all(): array
    {
        return Selection::of($this->mandates)->copies();
    }

    /**
     * @throws InvariantViolation "mandate.duplicate_active"
     */
    #[Override]
    public function save(Mandate $mandate): void
    {
        $this->assertSingleActive($mandate);
        $snapshot = clone $mandate;
        $snapshot->releaseEvents();
        $this->mandates[$mandate->id()->value()] = $snapshot;
    }

    #[Override]
    public function get(MandateId $id): Mandate
    {
        $stored = $this->mandates[$id->value()] ?? throw RecordNotFound::withIdentifier('mandate', $id->value());

        return clone $stored;
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
            ->copies()[0] ?? null;
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
            ->copies();
    }

    #[Override]
    public function delete(MandateId $id): void
    {
        unset($this->mandates[$id->value()]);
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
