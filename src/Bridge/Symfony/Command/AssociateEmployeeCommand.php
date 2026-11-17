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
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\AssociateEmployee;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\AssociateEmployeeHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Input\EmployeeCreationFactory;

/**
 * Associates an employee to an employer, creating the employee account when the data allow it (method 023). The birth
 * date of the employer and the NIR of the employee are asked in a hidden prompt; the identity, the address and the other
 * creation data come from JSON files (ADR 0013). A repeated association is answered by a non-blocking code, not an error.
 */
#[AsCommand(name: 'urssaf:paje:employee:associate', description: 'Associate an employee to an employer, creating the employee when needed (method 023)')]
final class AssociateEmployeeCommand extends Command
{
    private const OPTION_CREATION_PAYLOAD = 'creation-payload';

    /**
     * @param list<string> $configuredTenants
     */
    public function __construct(
        private readonly AssociateEmployeeHandler $associate,
        private readonly array $configuredTenants,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        CommandPersonalInputs::addEmployer($this);
        CommandPersonalInputs::addEmployee($this);
        CommandOptions::addTenant($this, 'Tenant id (default: the only configured tenant)');
        CommandPayloads::add($this, self::OPTION_CREATION_PAYLOAD, 'JSON file with the data to create the employee when URSSAF does not know it: see Domain\Employee\Input\EmployeeCreationFactory');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $tenant = CommandTenants::tenantOrSingleConfigured($input, $this->configuredTenants);
            $creation = (new EmployeeCreationFactory())->fromInput(CommandPayloads::read($input, self::OPTION_CREATION_PAYLOAD) ?? []);
            $selection = CommandPersonalInputs::selectEmployee($input);
            $helper = new QuestionHelper();
            $employer = CommandPersonalInputs::employer($input, $output, $helper);
            $employee = CommandPersonalInputs::promptEmployee($selection, $input, $output, $helper);

            CommandReports::renderFields($output, CommandViews::association(($this->associate)(new AssociateEmployee($tenant, $employer, $employee, $creation))));

            return Command::SUCCESS;
        });
    }
}
