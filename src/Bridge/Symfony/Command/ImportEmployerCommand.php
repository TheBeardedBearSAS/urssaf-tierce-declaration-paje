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
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ImportEmployer;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ImportEmployerHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;

/**
 * Starts to follow an employer already known to the host, without any URSSAF call. The birth date of the employer is asked in a hidden prompt (ADR 0013).
 */
#[AsCommand(name: 'urssaf:paje:employer:import', description: 'Track an existing employer (no URSSAF call)')]
final class ImportEmployerCommand extends Command
{
    private const OPTION_REFERENCE = 'reference';

    /**
     * @param list<string> $configuredTenants
     */
    public function __construct(
        private readonly ImportEmployerHandler $handler,
        private readonly array $configuredTenants,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        CommandPersonalInputs::addEmployer($this);
        CommandOptions::addTenant($this, 'Tenant id (default: the only configured tenant)');
        $this->addOption(self::OPTION_REFERENCE, null, InputOption::VALUE_REQUIRED, 'Your reference of the employer (required)');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $tenant = CommandTenants::tenantOrSingleConfigured($input, $this->configuredTenants);
            $reference = CommandOptions::text($input, self::OPTION_REFERENCE) ?? throw new InvalidOptionException('The --reference option is required.');
            $externalReference = ExternalReference::fromString($reference);
            $employer = CommandPersonalInputs::employer($input, $output, new QuestionHelper());

            CommandReports::renderFields($output, CommandViews::trackedAccount(($this->handler)(new ImportEmployer($tenant, $externalReference, $employer))));

            return Command::SUCCESS;
        });
    }
}
