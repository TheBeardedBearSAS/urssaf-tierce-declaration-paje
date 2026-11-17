<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command;

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\DeclarationView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\DeclarationLocator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\RecordedEvents;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;

/**
 * Applies the operator's finding to a record in state outcome_unknown (ADR 0003). Nothing is sent to URSSAF.
 */
final readonly class ResolveDeclarationOutcomeHandler
{
    public function __construct(
        private DeclarationLocator $locator,
        private DeclarationRecordRepository $records,
        private Clock $clock,
        private UnitOfWork $unitOfWork,
    ) {}

    /**
     * @throws RecordNotFound         "declaration_record.not_found"
     * @throws InvalidStateTransition the record is not in state outcome_unknown
     * @throws InvalidValue           "document_reference.empty"
     * @throws InvariantViolation     "declaration_record.concurrent_modification": the record changed since it was read,
     *                                nothing was saved (read it again and decide again)
     */
    public function __invoke(ResolveDeclarationOutcome $command): DeclarationView
    {
        $record = $this->locator->locate($command->tenantId, $command->declaration);
        $this->unitOfWork->run(function (RecordedEvents $events) use ($command, $record): void {
            $this->resolve($record, $command->documentReference, Instant::fromDateTime($this->clock->now()));
            $this->records->save($record);
            $events->record(...$record->releaseEvents());
        });

        return DeclarationView::fromRecord($record);
    }

    /**
     * @throws InvalidValue
     * @throws InvalidStateTransition
     */
    private function resolve(DeclarationRecord $record, ?string $documentReference, Instant $now): void
    {
        if ($documentReference === null) {
            $record->resolveAsNotReceived($now);

            return;
        }
        $record->resolveAsDeclared($documentReference, $now);
    }
}
