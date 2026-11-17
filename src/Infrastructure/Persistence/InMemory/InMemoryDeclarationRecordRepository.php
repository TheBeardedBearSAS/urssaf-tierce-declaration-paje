<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory;

use DateInterval;
use Override;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Process-local {@see DeclarationRecordRepository} for tests and demos.
 *
 * Identity semantics: like a database, it stores a snapshot. {@see self::save()} copies the aggregate (without its
 * pending domain events, which are never persisted) and every read returns a new copy, so a change is only visible
 * once saved. Because the stored copy is private, {@see self::claimForSubmission()} can apply
 * {@see DeclarationRecord::startSubmission()} to it without touching the events pending on the caller's instance (the
 * transition records no event, as the port requires).
 *
 * Lists are ordered as documented by the port; records that compare equal keep their insertion order.
 *
 * One method per query of the port plus the claim and erasure helpers, hence the method count and complexity.
 *
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 *
 * Optimistic concurrency: see {@see VersionTokens}. Saving a copy another writer changed since it was read is refused
 * (a never-read instance is refused too when the identifier is already stored).
 */
final class InMemoryDeclarationRecordRepository implements DeclarationRecordRepository
{
    /** @var array<string, DeclarationRecord> stored snapshots by record id, in insertion order */
    private array $records = [];

    private readonly VersionTokens $versions;

    public function __construct()
    {
        $this->versions = new VersionTokens(
            DeclarationRecordRepository::CONCURRENT_MODIFICATION,
            'The declaration record was changed by another writer since it was read.',
        );
    }

    /**
     * Copies of every stored record (monitoring, tests).
     *
     * @return list<DeclarationRecord>
     */
    public function all(): array
    {
        return Selection::of($this->records)->copies($this->adopt(...));
    }

    /**
     * @throws InvariantViolation "declaration_record.concurrent_modification" (saved, claimed or erased by another writer
     *                            since this instance was read) or "declaration_record.duplicate_external_reference"
     */
    #[Override]
    public function save(DeclarationRecord $record): void
    {
        $id = $record->id()->value();
        $this->versions->assertCurrent($record, $id);
        $this->assertUnique($record);
        $snapshot = clone $record;
        $snapshot->releaseEvents();
        $this->records[$id] = $snapshot;
        $this->versions->saved($record, $id);
    }

    #[Override]
    public function get(DeclarationRecordId $id): DeclarationRecord
    {
        $stored = $this->records[$id->value()] ?? throw RecordNotFound::withIdentifier('declaration_record', $id->value());

        return $this->versions->track(clone $stored, $id->value());
    }

    #[Override]
    public function findByExternalReference(TenantId $tenantId, ExternalReference $externalReference): ?DeclarationRecord
    {
        return Selection::of($this->records)
            ->where(
                static fn(DeclarationRecord $record): bool => $record->tenantId()->equals($tenantId)
                    && $record->externalReference()->equals($externalReference),
            )
            ->copies($this->adopt(...))[0] ?? null;
    }

    #[Override]
    public function findByState(TenantId $tenantId, DeclarationState $state, int $limit): array
    {
        return Selection::of($this->records)
            ->where(
                static fn(DeclarationRecord $record): bool => $record->tenantId()->equals($tenantId)
                    && $record->state() === $state,
            )
            ->sortedBy(
                static fn(DeclarationRecord $a, DeclarationRecord $b): int => Selection::compareInstants(
                    $a->updatedAt(),
                    $b->updatedAt(),
                ),
            )
            ->limit($limit)
            ->copies($this->adopt(...));
    }

    #[Override]
    public function findStaleSubmissions(Instant $olderThan, int $limit): array
    {
        return Selection::of($this->records)
            ->where(
                static fn(DeclarationRecord $record): bool => $record->state() === DeclarationState::Submitting
                    && $record->status()->isLastAttemptStartedBefore($olderThan, new DateInterval('PT0S')),
            )
            ->sortedBy(
                static fn(DeclarationRecord $a, DeclarationRecord $b): int => Selection::compareInstants(
                    $a->lastAttemptAt(),
                    $b->lastAttemptAt(),
                ),
            )
            ->limit($limit)
            ->copies($this->adopt(...));
    }

    #[Override]
    public function countByState(TenantId $tenantId): array
    {
        $counts = [];
        foreach (DeclarationState::cases() as $state) {
            $counts[$state->value] = 0;
        }
        foreach ($this->records as $record) {
            if (!$record->tenantId()->equals($tenantId)) {
                continue;
            }
            ++$counts[$record->state()->value];
        }

        return $counts;
    }

    #[Override]
    public function tenantsWithState(DeclarationState $state): array
    {
        return array_map(
            static fn(DeclarationRecord $record): TenantId => $record->tenantId(),
            Selection::of($this->records)
                ->where(static fn(DeclarationRecord $record): bool => $record->state() === $state)
                ->distinctBy(static fn(DeclarationRecord $record): string => $record->tenantId()->value())
                ->sortedBy(
                    static fn(DeclarationRecord $a, DeclarationRecord $b): int => strcmp(
                        $a->tenantId()->value(),
                        $b->tenantId()->value(),
                    ),
                )
                ->all(),
        );
    }

    #[Override]
    public function claimForSubmission(DeclarationRecordId $id, Instant $now): bool
    {
        $stored = $this->records[$id->value()] ?? null;
        if ($stored === null || $stored->state() !== DeclarationState::Pending) {
            return false;
        }

        $stored->startSubmission($now);
        $this->versions->bump($id->value());

        return true;
    }

    #[Override]
    public function delete(DeclarationRecordId $id): void
    {
        unset($this->records[$id->value()]);
        $this->versions->forget($id->value());
    }

    #[Override]
    public function countByEmployer(TenantId $tenantId, PajeEmployerNumber $employerNumber): array
    {
        $counts = [];
        foreach (DeclarationState::cases() as $state) {
            $counts[$state->value] = 0;
        }
        foreach ($this->records as $record) {
            if (!self::belongsTo($record, $tenantId, $employerNumber)) {
                continue;
            }
            ++$counts[$record->state()->value];
        }

        return $counts;
    }

    #[Override]
    public function deleteByEmployer(TenantId $tenantId, PajeEmployerNumber $employerNumber, array $states): array
    {
        $ids = [];
        $selected = Selection::of($this->records)
            ->where(
                static fn(DeclarationRecord $record): bool => self::belongsTo($record, $tenantId, $employerNumber)
                    && \in_array($record->state(), $states, true),
            )
            ->sortedBy(static function (DeclarationRecord $a, DeclarationRecord $b): int {
                $byCreation = Selection::compareInstants($a->createdAt(), $b->createdAt());

                return $byCreation === 0 ? strcmp($a->id()->value(), $b->id()->value()) : $byCreation;
            })
            ->all();
        foreach ($selected as $record) {
            $ids[] = $record->id();
            unset($this->records[$record->id()->value()]);
            $this->versions->forget($record->id()->value());
        }

        return $ids;
    }

    private static function belongsTo(DeclarationRecord $record, TenantId $tenantId, PajeEmployerNumber $employerNumber): bool
    {
        return $record->tenantId()->equals($tenantId)
            && $record->snapshot()->employerNumber()->equals($employerNumber);
    }

    private function adopt(DeclarationRecord $copy): DeclarationRecord
    {
        return $this->versions->track($copy, $copy->id()->value());
    }

    private function assertUnique(DeclarationRecord $record): void
    {
        foreach ($this->records as $stored) {
            if ($stored->id()->equals($record->id()) || !$stored->tenantId()->equals($record->tenantId())) {
                continue;
            }
            if ($stored->externalReference()->equals($record->externalReference())) {
                throw InvariantViolation::because(
                    DeclarationRecordRepository::DUPLICATE_EXTERNAL_REFERENCE,
                    'A declaration record already exists for this external reference.',
                    ['external_reference' => $record->externalReference()->value()],
                );
            }
        }
    }
}
