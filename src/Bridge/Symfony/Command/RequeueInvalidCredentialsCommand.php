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
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RequeueAfterInvalidCredentials;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RequeueAfterInvalidCredentialsHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;

/**
 * Moves the declarations refused for invalid credentials back to pending, once the credentials are fixed. They are then
 * sent again with urssaf:paje:declare (the record never holds the payload, ADR 0007).
 */
#[AsCommand(name: 'urssaf:paje:declarations:requeue-invalid-credentials', description: 'Move the declarations refused for invalid credentials back to pending (after fixing the credentials)')]
final class RequeueInvalidCredentialsCommand extends Command
{
    /**
     * @param list<string> $configuredTenants
     */
    public function __construct(
        private readonly DeclarationRecordRepository $declarations,
        private readonly RequeueAfterInvalidCredentialsHandler $requeue,
        private readonly WorkflowPolicy $policy,
        private readonly OperationLocks $locks,
        private readonly array $configuredTenants,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        CommandOptions::addTenant($this, 'Tenant id (default: the only configured tenant)');
        CommandOptions::addLimit($this);
        CommandOptions::addDryRun($this);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $tenant = CommandTenants::tenantOrSingleConfigured($input, $this->configuredTenants);
            $limit = CommandOptions::limit($input);
            if (CommandOptions::dryRun($input)) {
                return CommandReports::renderPlan($output, array_map(
                    static fn(DeclarationRecord $record): array => [$tenant->value(), $record->id()->value()],
                    $this->declarations->findByState($tenant, DeclarationState::InvalidCredentials, $this->policy->pageSize($limit)),
                ));
            }

            return $this->locks->exclusively(
                'requeue-invalid-credentials',
                $tenant->value(),
                function () use ($output, $tenant, $limit): int {
                    $report = ($this->requeue)(new RequeueAfterInvalidCredentials($tenant, $limit));
                    $table = new Table($output);
                    $table->setHeaders(['Records', 'Requeued']);
                    $table->setRows([['declarations', $report->declarations]]);
                    $table->render();
                    $output->writeln('The requeued declarations are pending: send them again with urssaf:paje:declare.');

                    return Command::SUCCESS;
                },
                static fn(string $resource): int => CommandReports::renderBusy($output, $resource),
            );
        });
    }
}
