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
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\EstimateContributions;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\EstimateContributionsHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationActivity;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\Input\EstimateRequestFactory;

/**
 * Estimates the contributions of a salary (methods 040 and 041); writes nothing. The data come from a JSON file
 * (--payload), never from the command line (ADR 0013): see Domain\Estimate\Input\EstimateRequestFactory.
 */
#[AsCommand(name: 'urssaf:paje:estimate', description: 'Estimate the contributions of a salary (methods 040 and 041); writes nothing')]
final class EstimateContributionsCommand extends Command
{
    private const OPTION_PAYLOAD = 'payload';

    /**
     * @param list<string> $configuredTenants
     */
    public function __construct(
        private readonly EstimateContributionsHandler $estimate,
        private readonly array $configuredTenants,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        CommandOptions::addTenant($this, 'Tenant id (default: the only configured tenant)');
        CommandOptions::addActivity($this);
        CommandPayloads::add($this, self::OPTION_PAYLOAD, 'JSON file with the data of the estimate (required)');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $tenant = CommandTenants::tenantOrSingleConfigured($input, $this->configuredTenants);
            $activity = CommandOptions::activity($input);
            $payload = CommandPayloads::require($input, self::OPTION_PAYLOAD);
            $factory = new EstimateRequestFactory();
            $request = $activity === DeclarationActivity::Childminder ? $factory->childminder($payload) : $factory->homeCare($payload);

            CommandReports::renderFields($output, CommandViews::estimate(($this->estimate)(new EstimateContributions($tenant, $request))));

            return Command::SUCCESS;
        });
    }
}
