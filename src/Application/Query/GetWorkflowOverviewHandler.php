<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query;

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\TenantOverview;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\StateCount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\WorkflowStatistics;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * The records by tenant and state, for dashboards and alerting (ADR 0003).
 */
final readonly class GetWorkflowOverviewHandler
{
    public function __construct(private WorkflowStatistics $statistics) {}

    /**
     * @return list<TenantOverview> one entry per tenant (sorted by id) that has records, or exactly one for the requested tenant
     */
    public function __invoke(GetWorkflowOverview $query): array
    {
        $employers = $this->byTenant($this->statistics->employerStages(), $query);
        $mandates = $this->byTenant($this->statistics->mandateStates(), $query);
        $declarations = $this->byTenant($this->statistics->declarationStates(), $query);
        // A numeric tenant id ("123") becomes an int array key: the keys are cast back to strings explicitly.
        $tenants = $query->tenantId instanceof TenantId
            ? [$query->tenantId->value()]
            : array_map(strval(...), array_keys($employers + $mandates + $declarations));
        sort($tenants, SORT_STRING);
        $overviews = [];
        foreach ($tenants as $tenant) {
            $overviews[] = new TenantOverview(
                $tenant,
                [...self::zeros(EmployerStage::cases()), ...$employers[$tenant] ?? []],
                [...self::zeros(MandateState::cases()), ...$mandates[$tenant] ?? []],
                [...self::zeros(DeclarationState::cases()), ...$declarations[$tenant] ?? []],
            );
        }

        return $overviews;
    }

    /**
     * @param list<StateCount> $counts
     *
     * @return array<string, array<string, int>>
     */
    private function byTenant(array $counts, GetWorkflowOverview $query): array
    {
        $byTenant = [];
        foreach ($counts as $count) {
            if ($query->tenantId instanceof TenantId && !$count->tenantId->equals($query->tenantId)) {
                continue;
            }
            $byTenant[$count->tenantId->value()][$count->state] = $count->count;
        }

        return $byTenant;
    }

    /**
     * @param list<EmployerStage>|list<MandateState>|list<DeclarationState> $states
     *
     * @return array<string, int>
     */
    private static function zeros(array $states): array
    {
        return array_fill_keys(
            array_map(static fn(EmployerStage|MandateState|DeclarationState $state): string => $state->value, $states),
            0,
        );
    }
}
