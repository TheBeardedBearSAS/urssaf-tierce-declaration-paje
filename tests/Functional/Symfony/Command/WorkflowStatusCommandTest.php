<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Scenario;

final class WorkflowStatusCommandTest extends TestCase
{
    private const NAME = 'urssaf:paje:status';

    public function testTheRecordsAreCountedByTenantAndState(): void
    {
        $harness = Harness::boot();
        Scenario::outcomeUnknownDeclaration($harness);
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute([]));

        static::assertSame([
            ['Employers'],
            ['Tenant', 'tracked', 'mandate_registered', 'mandate_cancelled'],
            ['tenant-1', '0', '1', '0'],
            ['Mandates'],
            ['Tenant', 'registered', 'cancelled'],
            ['tenant-1', '1', '0'],
            ['Declarations'],
            ['Tenant', 'pending', 'submitting', 'declared', 'rejected', 'already_declared', 'outcome_unknown', 'error', 'invalid_credentials', 'Needing attention'],
            ['tenant-1', '0', '0', '0', '0', '0', '1', '0', '0', '1'],
        ], self::tables($command->getDisplay()));
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_020));
    }

    public function testTheJsonFormatIsMeantForMonitoring(): void
    {
        $harness = Harness::boot();
        Scenario::declaration($harness);
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute(['--format' => 'json', '--tenant' => 'tenant-1']));

        static::assertSame([[
            'tenant_id' => 'tenant-1',
            'employers' => ['tracked' => 0, 'mandate_registered' => 1, 'mandate_cancelled' => 0],
            'mandates' => ['registered' => 1, 'cancelled' => 0],
            'declarations' => ['pending' => 0, 'submitting' => 0, 'declared' => 1, 'rejected' => 0, 'already_declared' => 0, 'outcome_unknown' => 0, 'error' => 0, 'invalid_credentials' => 0],
            'needing_attention' => 0,
        ]], json_decode($command->getDisplay(), true, 512, JSON_THROW_ON_ERROR));
        static::assertStringContainsString("\n    {\n        \"tenant_id\"", $command->getDisplay());
    }

    public function testATenantWithoutRecordsIsListedWithZeroes(): void
    {
        $command = Harness::boot()->command(self::NAME);

        static::assertSame(0, $command->execute(['--tenant' => 'tenant-1', '--format' => 'table']));

        static::assertSame(['tenant-1', '0', '0', '0'], self::tables($command->getDisplay())[2]);
    }

    public function testNoTenantWithRecordsGivesEmptyTables(): void
    {
        $command = Harness::boot()->command(self::NAME);

        static::assertSame(0, $command->execute([]));

        static::assertCount(6, self::tables($command->getDisplay()));
    }

    public function testAnUnknownFormatExitsWithTwo(): void
    {
        $command = Harness::boot()->command(self::NAME);

        static::assertSame(2, $command->execute(['--format' => 'xml']));

        static::assertStringContainsString('The --format option must be "table" or "json".', $command->getDisplay());
    }

    /**
     * The titles and rows of the tables printed by the command.
     *
     * @return list<list<string>>
     */
    private static function tables(string $display): array
    {
        $lines = [];
        foreach (explode("\n", $display) as $line) {
            if (str_starts_with($line, '|')) {
                $lines[] = array_map(trim(...), explode('|', trim($line, '|')));

                continue;
            }
            if ($line !== '' && !str_starts_with($line, '+')) {
                $lines[] = [$line];
            }
        }

        return $lines;
    }
}
