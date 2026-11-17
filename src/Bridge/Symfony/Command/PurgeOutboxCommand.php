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
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\PurgeOutbox;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\PurgeOutboxHandler;

/**
 * Deletes the outbox entries dispatched for longer than the retention ("outbox.retention", held by the workflow policy).
 * Safety cron: daily (registered only with "outbox.enabled").
 */
#[AsCommand(name: 'urssaf:paje:outbox:purge', description: 'Delete the outbox entries dispatched for longer than the retention')]
final class PurgeOutboxCommand extends Command
{
    public function __construct(private readonly PurgeOutboxHandler $purge)
    {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($output): int {
            $output->writeln(\sprintf('%d dispatched outbox entries deleted.', ($this->purge)(new PurgeOutbox(null))));

            return Command::SUCCESS;
        });
    }
}
