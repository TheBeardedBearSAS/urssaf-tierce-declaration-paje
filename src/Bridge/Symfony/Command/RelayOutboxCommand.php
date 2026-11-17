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
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RelayOutbox;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RelayOutboxHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Lock\OperationLocks;

/**
 * Delivers the pending integration events of the outbox (docs/integration-events.md). Exits with 1 when a delivery failed.
 * Safety cron: every minute (registered only with "outbox.enabled").
 */
#[AsCommand(name: 'urssaf:paje:outbox:relay', description: 'Deliver the pending integration events of the outbox')]
final class RelayOutboxCommand extends Command
{
    public function __construct(private readonly RelayOutboxHandler $relay, private readonly OperationLocks $locks)
    {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        CommandOptions::addLimit($this);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $limit = CommandOptions::limit($input);

            return $this->locks->exclusively(
                'outbox.relay',
                '*',
                function () use ($output, $limit): int {
                    $report = ($this->relay)(new RelayOutbox($limit));
                    $table = new Table($output);
                    $table->setHeaders(['Dispatched', 'Failed', 'Deferred']);
                    $table->setRows([[$report->dispatched, $report->failed, $report->deferred]]);
                    $table->render();

                    return $report->failed > 0 ? Command::FAILURE : Command::SUCCESS;
                },
                static fn(string $resource): int => CommandReports::renderBusy($output, $resource),
            );
        });
    }
}
