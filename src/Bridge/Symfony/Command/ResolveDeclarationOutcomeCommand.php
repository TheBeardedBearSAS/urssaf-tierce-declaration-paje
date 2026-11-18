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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ResolveDeclarationOutcome;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ResolveDeclarationOutcomeHandler;

/**
 * Settles a declaration left in "outcome_unknown" after the operator checked on pajemploi.urssaf.fr (ADR 0003): either
 * URSSAF holds it (give its referenceDocumentaire), or it never arrived (declare it again with the same external
 * reference and content).
 */
#[AsCommand(name: 'urssaf:paje:declaration:resolve', description: 'Settle an outcome_unknown declaration after a manual check (ADR 0003)')]
final class ResolveDeclarationOutcomeCommand extends Command
{
    private const OPTION_DECLARED_AS = 'declared-as';
    private const OPTION_NOT_RECEIVED = 'not-received';

    /**
     * @param list<string> $configuredTenants
     */
    public function __construct(
        private readonly ResolveDeclarationOutcomeHandler $resolve,
        private readonly array $configuredTenants,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        CommandOptions::addTenant($this, 'Tenant id (default: the only configured tenant)');
        CommandSelectors::addDeclarationSelector($this);
        $this->addOption(self::OPTION_DECLARED_AS, null, InputOption::VALUE_REQUIRED, 'The referenceDocumentaire URSSAF gave to the declaration');
        $this->addOption(self::OPTION_NOT_RECEIVED, null, InputOption::VALUE_NONE, 'URSSAF never received the declaration');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $tenant = CommandTenants::tenantOrSingleConfigured($input, $this->configuredTenants);
            $selector = CommandSelectors::declarationSelector($input);
            $declaredAs = CommandOptions::text($input, self::OPTION_DECLARED_AS);
            $notReceived = $input->getOption(self::OPTION_NOT_RECEIVED) === true;
            $declared = $declaredAs !== null;
            if ($declared === $notReceived) {
                throw new InvalidOptionException('Give one of --declared-as or --not-received.');
            }

            $declaration = ($this->resolve)($declared
                ? ResolveDeclarationOutcome::asDeclared($tenant, $selector, $declaredAs)
                : ResolveDeclarationOutcome::asNotReceived($tenant, $selector));
            CommandReports::renderFields($output, CommandViews::declaration($declaration));
            if ($notReceived) {
                $output->writeln('Declare it again with urssaf:paje:declare (same external reference and content).');
            }

            return Command::SUCCESS;
        });
    }
}
