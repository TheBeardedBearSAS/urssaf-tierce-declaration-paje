<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ImportMandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ImportMandateHandler;

/**
 * Records the mandate of a tracked employer without any URSSAF call: after an accepted 030 whose recording failed, or a mandate registered on pajemploi.urssaf.fr. Idempotent, under the lock of the employer (ADR 0006).
 */
#[AsCommand(name: 'urssaf:paje:mandate:import', description: 'Record a mandate obtained outside the package (no URSSAF call)')]
final class ImportMandateCommand extends Command
{
    /**
     * @param list<string> $configuredTenants
     */
    public function __construct(
        private readonly ImportMandateHandler $handler,
        private readonly CommandLocks $locks,
        private readonly array $configuredTenants,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        CommandOptions::addTenant($this, 'Tenant id (default: the only configured tenant)');
        CommandSelectors::addEmployerSelector($this);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $tenant = CommandTenants::tenantOrSingleConfigured($input, $this->configuredTenants);
            $selector = CommandSelectors::employerSelector($input);

            return $this->locks->forEmployer(
                $output,
                $tenant,
                $selector,
                CommandLocks::MANDATE,
                function () use ($output, $tenant, $selector): int {
                    CommandReports::renderFields($output, CommandViews::mandate(($this->handler)(new ImportMandate($tenant, $selector))));

                    return Command::SUCCESS;
                },
            );
        });
    }
}
