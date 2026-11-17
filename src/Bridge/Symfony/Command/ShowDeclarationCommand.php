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
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\GetDeclaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\GetDeclarationHandler;

/**
 * Shows the local record of a declaration: state, attempts, error codes, result. Local read, no URSSAF call; error codes
 * only, never the messages of URSSAF.
 */
#[AsCommand(name: 'urssaf:paje:declaration:show', description: 'Show the local record of a declaration (no URSSAF call)')]
final class ShowDeclarationCommand extends Command
{
    /**
     * @param list<string> $configuredTenants
     */
    public function __construct(
        private readonly GetDeclarationHandler $get,
        private readonly array $configuredTenants,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        CommandOptions::addTenant($this, 'Tenant id (default: the only configured tenant)');
        CommandSelectors::addDeclarationSelector($this);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $tenant = CommandTenants::tenantOrSingleConfigured($input, $this->configuredTenants);

            CommandReports::renderFields($output, CommandViews::declaration(($this->get)(new GetDeclaration($tenant, CommandSelectors::declarationSelector($input)))));

            return Command::SUCCESS;
        });
    }
}
