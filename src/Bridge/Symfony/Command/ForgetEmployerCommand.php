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
use Symfony\Component\Console\Question\ConfirmationQuestion;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ForgetEmployer;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ForgetEmployerHandler;

/**
 * Erases a tracked employer, its mandates and its declaration records from the local store (GDPR, ADR 0015), after a
 * confirmation (or --force). Nothing is sent to URSSAF. The output holds the account id and counts only (ADR 0007).
 * "--force" only skips the confirmation: the two opt-ins override the workflow policy for this run. Runs under the lock of
 * the employer (ADR 0006).
 */
#[AsCommand(name: 'urssaf:paje:employer:forget', description: 'Erase a tracked employer with its mandates and declaration records from the local store (GDPR; nothing is sent to URSSAF)')]
final class ForgetEmployerCommand extends Command
{
    private const OPTION_FORCE = 'force';
    private const OPTION_ALLOW_ACTIVE_MANDATE = 'allow-active-mandate';
    private const OPTION_ALLOW_UNKNOWN_OUTCOMES = 'allow-unknown-outcomes';

    /**
     * @param list<string> $configuredTenants
     */
    public function __construct(
        private readonly ForgetEmployerHandler $handler,
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
        $this->addOption(self::OPTION_ALLOW_ACTIVE_MANDATE, null, InputOption::VALUE_NONE, 'Erase the employer even if its mandate is still active (it stays active at URSSAF: cancel it first with urssaf:paje:mandate:cancel)');
        $this->addOption(self::OPTION_ALLOW_UNKNOWN_OUTCOMES, null, InputOption::VALUE_NONE, 'Erase the declarations whose outcome is unknown without resolving them');
        $this->addOption(self::OPTION_FORCE, null, InputOption::VALUE_NONE, 'Do not ask for a confirmation (required when not interactive)');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $tenant = CommandTenants::tenantOrSingleConfigured($input, $this->configuredTenants);
            $selector = CommandSelectors::employerSelector($input);
            if ($input->getOption(self::OPTION_FORCE) !== true) {
                if (!$input->isInteractive()) {
                    throw new InvalidOptionException('Erasing an employer needs a confirmation: use --force when not interactive.');
                }
                $question = new ConfirmationQuestion('Erase this employer, its mandates and its declaration records from the local store? This cannot be undone and nothing is sent to URSSAF. [y/N] ', false);
                if ((new QuestionHelper())->ask($input, $output, $question) !== true) {
                    $output->writeln('Aborted: nothing was erased.');

                    return Command::SUCCESS;
                }
            }

            return $this->locks->forEmployer(
                $output,
                $tenant,
                $selector,
                CommandLocks::MANDATE,
                function () use ($input, $output, $tenant, $selector): int {
                    $erasure = ($this->handler)(new ForgetEmployer(
                        $tenant,
                        $selector,
                        $input->getOption(self::OPTION_ALLOW_ACTIVE_MANDATE) === true ? true : null,
                        $input->getOption(self::OPTION_ALLOW_UNKNOWN_OUTCOMES) === true ? true : null,
                    ));
                    CommandReports::renderFields($output, [
                        ['Account id', $erasure->accountId],
                        ['Mandates erased', \sprintf('%d', \count($erasure->mandateIds))],
                        ['Declarations erased', \sprintf('%d', \count($erasure->declarationRecordIds))],
                    ]);
                    $output->writeln('Erased from the local store only: nothing was sent to URSSAF.');

                    return Command::SUCCESS;
                },
            );
        });
    }
}
