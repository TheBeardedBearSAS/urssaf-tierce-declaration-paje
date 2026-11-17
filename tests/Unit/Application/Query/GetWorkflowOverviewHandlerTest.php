<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Query;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\TenantOverview;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\StateCount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\GetWorkflowOverview;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\GetWorkflowOverviewHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\FixedWorkflowStatistics;

final class GetWorkflowOverviewHandlerTest extends TestCase
{
    private const NO_EMPLOYER = ['tracked' => 0, 'mandate_registered' => 0, 'mandate_cancelled' => 0];
    private const NO_MANDATE = ['registered' => 0, 'cancelled' => 0];
    private const NO_DECLARATION = [
        'pending' => 0,
        'submitting' => 0,
        'declared' => 0,
        'rejected' => 0,
        'already_declared' => 0,
        'outcome_unknown' => 0,
        'error' => 0,
        'invalid_credentials' => 0,
    ];

    public function testEveryTenantWithRecordsIsListedInOrderWithEveryState(): void
    {
        $overviews = self::handler()(GetWorkflowOverview::allTenants());

        static::assertSame(
            ['tenant-a', 'tenant-b', 'tenant-c'],
            array_map(static fn(TenantOverview $overview): string => $overview->tenantId, $overviews),
        );
        static::assertSame(array_replace(self::NO_EMPLOYER, ['tracked' => 2, 'mandate_registered' => 1]), $overviews[0]->employers);
        static::assertSame(array_keys(self::NO_EMPLOYER), array_keys($overviews[0]->employers));
        static::assertSame(array_replace(self::NO_MANDATE, ['registered' => 4]), $overviews[0]->mandates);
        static::assertSame(array_keys(self::NO_MANDATE), array_keys($overviews[0]->mandates));
        static::assertSame(array_replace(self::NO_DECLARATION, ['declared' => 4, 'error' => 1]), $overviews[0]->declarations);
        static::assertSame(array_keys(self::NO_DECLARATION), array_keys($overviews[0]->declarations));
        static::assertSame(self::NO_EMPLOYER, $overviews[1]->employers);
        static::assertSame(array_replace(self::NO_MANDATE, ['cancelled' => 3]), $overviews[1]->mandates);
        static::assertSame(self::NO_DECLARATION, $overviews[1]->declarations);
        static::assertSame(array_replace(self::NO_DECLARATION, ['invalid_credentials' => 1]), $overviews[2]->declarations);
    }

    public function testOneTenantIsListedEvenWithoutRecords(): void
    {
        $handler = self::handler();

        $known = $handler(GetWorkflowOverview::forTenant(TenantId::fromString('tenant-b')));
        $unknown = $handler(GetWorkflowOverview::forTenant(TenantId::fromString('tenant-z')));

        static::assertCount(1, $known);
        static::assertSame('tenant-b', $known[0]->tenantId);
        static::assertSame(array_replace(self::NO_MANDATE, ['cancelled' => 3]), $known[0]->mandates);
        static::assertSame(self::NO_EMPLOYER, $known[0]->employers);
        static::assertSame(self::NO_DECLARATION, $known[0]->declarations);
        static::assertCount(1, $unknown);
        static::assertSame('tenant-z', $unknown[0]->tenantId);
        static::assertSame(self::NO_EMPLOYER, $unknown[0]->employers);
        static::assertSame(self::NO_MANDATE, $unknown[0]->mandates);
        static::assertSame(self::NO_DECLARATION, $unknown[0]->declarations);
    }

    public function testNoRecordAtAllListsNoTenant(): void
    {
        static::assertSame([], (new GetWorkflowOverviewHandler(new FixedWorkflowStatistics([], [], [])))(GetWorkflowOverview::allTenants()));
    }

    public function testANumericTenantIdIsKeptAsAString(): void
    {
        $overviews = (new GetWorkflowOverviewHandler(new FixedWorkflowStatistics(
            [self::stateCount('123', 'tracked', 1)],
            [self::stateCount('45', 'registered', 2)],
            [self::stateCount('tenant-a', 'declared', 3)],
        )))(GetWorkflowOverview::allTenants());

        static::assertSame(
            ['123', '45', 'tenant-a'],
            array_map(static fn(TenantOverview $overview): string => $overview->tenantId, $overviews),
        );
        static::assertSame(array_replace(self::NO_EMPLOYER, ['tracked' => 1]), $overviews[0]->employers);
        static::assertSame(array_replace(self::NO_MANDATE, ['registered' => 2]), $overviews[1]->mandates);
    }

    public function testTheTenantsAreSortedByIdWhateverTheOrderOfTheStatistics(): void
    {
        $overviews = (new GetWorkflowOverviewHandler(new FixedWorkflowStatistics(
            [self::stateCount('tenant-b', 'tracked', 1)],
            [self::stateCount('tenant-a', 'registered', 1)],
            [self::stateCount('tenant-c', 'declared', 1), self::stateCount('tenant-a', 'declared', 1)],
        )))(GetWorkflowOverview::allTenants());

        static::assertSame(
            ['tenant-a', 'tenant-b', 'tenant-c'],
            array_map(static fn(TenantOverview $overview): string => $overview->tenantId, $overviews),
        );
    }

    public function testTheDeclarationsWaitingForAManualActionAreSummed(): void
    {
        $overviews = (new GetWorkflowOverviewHandler(new FixedWorkflowStatistics(
            [self::stateCount('t', 'tracked', 50)],
            [self::stateCount('t', 'registered', 60)],
            [
                self::stateCount('t', 'outcome_unknown', 1),
                self::stateCount('t', 'error', 2),
                self::stateCount('t', 'invalid_credentials', 4),
                self::stateCount('t', 'already_declared', 8),
                self::stateCount('t', 'rejected', 50),
                self::stateCount('t', 'pending', 50),
                self::stateCount('t', 'declared', 50),
            ],
        )))(GetWorkflowOverview::allTenants());

        static::assertSame(15, $overviews[0]->needingAttention());
    }

    private static function handler(): GetWorkflowOverviewHandler
    {
        return new GetWorkflowOverviewHandler(new FixedWorkflowStatistics(
            [
                self::stateCount('tenant-a', 'tracked', 2),
                self::stateCount('tenant-a', 'mandate_registered', 1),
            ],
            [
                self::stateCount('tenant-a', 'registered', 4),
                self::stateCount('tenant-b', 'cancelled', 3),
            ],
            [
                self::stateCount('tenant-a', 'declared', 4),
                self::stateCount('tenant-a', 'error', 1),
                self::stateCount('tenant-c', 'invalid_credentials', 1),
            ],
        ));
    }

    private static function stateCount(string $tenant, string $state, int $count): StateCount
    {
        return new StateCount(TenantId::fromString($tenant), $state, $count);
    }
}
