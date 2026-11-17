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
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\VerifyChildren;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\VerifyChildrenHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\Input\ChildListFactory;

/**
 * Checks the entitlement of the children of an employer with URSSAF (method 022); writes nothing. The children (names and
 * birth dates) come from a JSON list file, the birth date of the employer from a hidden prompt (ADR 0013). The output
 * holds the statuses in the order of the file, never a child identity.
 */
#[AsCommand(name: 'urssaf:paje:children:verify', description: 'Check the entitlement of the children of an employer (method 022); writes nothing')]
final class VerifyChildrenCommand extends Command
{
    private const OPTION_PAYLOAD = 'payload';

    /**
     * @param list<string> $configuredTenants
     */
    public function __construct(
        private readonly VerifyChildrenHandler $verify,
        private readonly array $configuredTenants,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        CommandPersonalInputs::addEmployer($this);
        CommandOptions::addTenant($this, 'Tenant id (default: the only configured tenant)');
        CommandPayloads::add($this, self::OPTION_PAYLOAD, 'JSON file with the list of the children (required): see Domain\Child\Input\ChildFactory');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $tenant = CommandTenants::tenantOrSingleConfigured($input, $this->configuredTenants);
            $children = (new ChildListFactory())->fromInput(CommandPayloads::require($input, self::OPTION_PAYLOAD));
            $employer = CommandPersonalInputs::employer($input, $output, new QuestionHelper());

            CommandReports::renderFields($output, CommandViews::childChecks(($this->verify)(new VerifyChildren($tenant, $employer, $children))));

            return Command::SUCCESS;
        });
    }
}
