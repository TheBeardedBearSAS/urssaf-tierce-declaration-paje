<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecord;

/**
 * Read model of a declaration record (primitives only, no personal data, no employer number). Instants are ISO 8601 UTC
 * strings. `employerAccountId` is null when the employer was not tracked when the declaration was recorded.
 */
final readonly class DeclarationView
{
    private function __construct(
        public string $recordId,
        public string $tenantId,
        public string $externalReference,
        public ?string $employerAccountId,
        public DeclarationSnapshotView $snapshot,
        public DeclarationStatusView $status,
        public string $createdAt,
    ) {}

    public static function fromRecord(DeclarationRecord $record): self
    {
        return new self(
            $record->id()->value(),
            $record->tenantId()->value(),
            $record->externalReference()->value(),
            $record->employerAccountId()?->value(),
            DeclarationSnapshotView::fromSnapshot($record->snapshot()),
            DeclarationStatusView::fromStatus($record->status()),
            $record->createdAt()->toIso8601(),
        );
    }
}
