<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake;

use Override;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\StateCount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\WorkflowStatistics;

/**
 * Statistics answering the counts it was built with.
 */
final readonly class FixedWorkflowStatistics implements WorkflowStatistics
{
    /**
     * @param list<StateCount> $employers
     * @param list<StateCount> $mandates
     * @param list<StateCount> $declarations
     */
    public function __construct(private array $employers, private array $mandates, private array $declarations) {}

    #[Override]
    public function employerStages(): array
    {
        return $this->employers;
    }

    #[Override]
    public function mandateStates(): array
    {
        return $this->mandates;
    }

    #[Override]
    public function declarationStates(): array
    {
        return $this->declarations;
    }
}
