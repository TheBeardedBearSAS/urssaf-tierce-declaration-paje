<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto;

/**
 * What ForgetEmployer erased: identifiers only (ADR 0007, ADR 0015).
 */
final readonly class EmployerErasure
{
    /**
     * @param list<string> $mandateIds           oldest registration first
     * @param list<string> $declarationRecordIds oldest creation first
     */
    public function __construct(
        public string $accountId,
        public array $mandateIds,
        public array $declarationRecordIds,
    ) {}
}
