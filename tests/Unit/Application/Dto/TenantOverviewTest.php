<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Dto;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\TenantOverview;

final class TenantOverviewTest extends TestCase
{
    public function testOnlyTheFourDeclarationStatesNeedAttention(): void
    {
        $overview = new TenantOverview(
            'tenant-1',
            ['tracked' => 50, 'mandate_registered' => 60, 'mandate_cancelled' => 70],
            ['registered' => 80, 'cancelled' => 90],
            [
                'pending' => 10_000,
                'submitting' => 20_000,
                'declared' => 40_000,
                'rejected' => 80_000,
                'already_declared' => 4000,
                'outcome_unknown' => 1,
                'error' => 20,
                'invalid_credentials' => 300,
            ],
        );

        static::assertSame(4321, $overview->needingAttention());
    }

    public function testToArrayKeepsTheKeysInOrder(): void
    {
        $employers = ['tracked' => 1];
        $mandates = ['registered' => 2];
        $declarations = ['outcome_unknown' => 3, 'error' => 0, 'invalid_credentials' => 1, 'already_declared' => 5];
        $overview = new TenantOverview('tenant-1', $employers, $mandates, $declarations);

        static::assertSame('tenant-1', $overview->tenantId);
        static::assertSame([
            'tenant_id' => 'tenant-1',
            'employers' => $employers,
            'mandates' => $mandates,
            'declarations' => $declarations,
            'needing_attention' => 9,
        ], $overview->toArray());
    }
}
