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
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\VerifyEmployer;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\VerifyEmployerHandler;

/**
 * Verifies an employer with URSSAF (method 020); writes nothing. The birth date of the employer is asked in a hidden
 * prompt (ADR 0013).
 */
#[AsCommand(name: 'urssaf:paje:employer:verify', description: 'Verify an employer with URSSAF (method 020); writes nothing')]
final class VerifyEmployerCommand extends Command
{
    /**
     * @param list<string> $configuredTenants
     */
    public function __construct(
        private readonly VerifyEmployerHandler $verify,
        private readonly array $configuredTenants,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        CommandPersonalInputs::addEmployer($this);
        CommandOptions::addTenant($this, 'Tenant id (default: the only configured tenant)');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $tenant = CommandTenants::tenantOrSingleConfigured($input, $this->configuredTenants);
            $employer = CommandPersonalInputs::employer($input, $output, new QuestionHelper());

            CommandReports::renderFields($output, CommandViews::employerStatus(($this->verify)(new VerifyEmployer($tenant, $employer))));

            return Command::SUCCESS;
        });
    }
}
