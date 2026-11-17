<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto;

/**
 * Outcome of a batch use case for one record: the record identifier (employer account id, declaration record id) and
 * the related URSSAF error codes (never a message or a description, ADR 0007).
 */
final readonly class BatchItem
{
    /**
     * @param list<string> $codes
     */
    public function __construct(
        public string $subject,
        public ItemOutcome $outcome,
        public array $codes = [],
    ) {}
}
