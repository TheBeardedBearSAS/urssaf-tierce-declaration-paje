<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto;

/**
 * Result of DeclareSalary: the declaration record as it stands and what the use case did (ADR 0003, ADR 0006).
 */
final readonly class DeclarationSubmission
{
    public function __construct(public DeclarationView $declaration, public ItemOutcome $outcome) {}
}
