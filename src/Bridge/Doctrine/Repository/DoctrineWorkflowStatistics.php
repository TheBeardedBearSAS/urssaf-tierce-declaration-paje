<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Repository;

use BackedEnum;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\StateCount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\WorkflowStatistics;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * One GROUP BY query per table, served by the indexes "(tenant_id, stage)", "(tenant_id, state)" and
 * "(tenant_id, state, updated_at)" of the three tables (pairs without record are omitted). Ordered by tenant then state,
 * so that the result does not depend on the platform.
 */
final readonly class DoctrineWorkflowStatistics implements WorkflowStatistics
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    #[Override]
    public function employerStages(): array
    {
        return $this->count(EmployerAccount::class, 'tracking.stage');
    }

    #[Override]
    public function mandateStates(): array
    {
        return $this->count(Mandate::class, 'state');
    }

    #[Override]
    public function declarationStates(): array
    {
        return $this->count(DeclarationRecord::class, 'status.state');
    }

    /**
     * @param class-string $entity
     *
     * @return list<StateCount>
     */
    private function count(string $entity, string $stateField): array
    {
        /** @var list<array{tenantId: TenantId, state: BackedEnum, total: int|string}> $rows */
        $rows = $this->entityManager
            ->createQuery(
                \sprintf(
                    'SELECT r.tenantId AS tenantId, r.%1$s AS state, COUNT(r.id) AS total FROM %2$s r'
                    . ' GROUP BY r.tenantId, r.%1$s ORDER BY r.tenantId ASC, r.%1$s ASC',
                    $stateField,
                    $entity,
                ),
            )
            ->getResult();

        return array_map(
            static fn(array $row): StateCount => new StateCount($row['tenantId'], (string) $row['state']->value, (int) $row['total']),
            $rows,
        );
    }
}
