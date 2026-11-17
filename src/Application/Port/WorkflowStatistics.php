<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port;

/**
 * Counts the records by tenant and state, for monitoring. A port of its own (not a repository method), so that a custom
 * persistence can do without it.
 */
interface WorkflowStatistics
{
    /**
     * @return list<StateCount> the employer accounts by tenant and stage (pairs without record are omitted)
     */
    public function employerStages(): array;

    /**
     * @return list<StateCount> the mandates by tenant and state (pairs without record are omitted)
     */
    public function mandateStates(): array;

    /**
     * @return list<StateCount> the declarations by tenant and state (pairs without record are omitted)
     */
    public function declarationStates(): array;
}
