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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\ArrayInput;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command\CommandOptions;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command\CommandTenants;

final class CommandTenantsTest extends TestCase
{
    /**
     * @param array<string, string> $values
     */
    private static function input(array $values): ArrayInput
    {
        $command = new Command('probe');
        CommandOptions::addTenant($command, 'Tenant');

        return new ArrayInput($values, $command->getDefinition());
    }

    public function testTheOptionWinsOverTheConfiguredTenants(): void
    {
        static::assertSame('tenant-2', CommandTenants::tenantOrSingleConfigured(self::input(['--tenant' => 'tenant-2']), ['tenant-1', 'tenant-3'])->value());
    }

    public function testTheOnlyConfiguredTenantIsTheDefault(): void
    {
        static::assertSame('tenant-1', CommandTenants::tenantOrSingleConfigured(self::input([]), ['tenant-1'])->value());
    }

    public function testSeveralConfiguredTenantsNeedTheOption(): void
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('The --tenant option is required (configured tenants: 2).');

        CommandTenants::tenantOrSingleConfigured(self::input([]), ['tenant-1', 'tenant-2']);
    }

    public function testNoConfiguredTenantNeedsTheOption(): void
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('The --tenant option is required (configured tenants: 0).');

        CommandTenants::tenantOrSingleConfigured(self::input([]), []);
    }
}
