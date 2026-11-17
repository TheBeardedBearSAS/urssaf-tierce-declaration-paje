<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\MessageHandler;

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\SyncEmployerStatus;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\SyncEmployerStatusHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\EmployerSelector;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Message\SyncEmployerStatusMessage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Refreshes one tracked employer (method 020), under the lock of the operation.
 */
final readonly class SyncEmployerStatusMessageHandler
{
    public function __construct(private SyncEmployerStatusHandler $sync, private OperationLocks $locks) {}

    public function __invoke(SyncEmployerStatusMessage $message): void
    {
        $this->locks->exclusively(
            'employer.sync-status',
            $message->employerAccountId,
            fn(): mixed => MessageFailures::guard(fn(): mixed => ($this->sync)(new SyncEmployerStatus(
                TenantId::fromString($message->tenantId),
                EmployerSelector::byAccountId(EmployerAccountId::fromString($message->employerAccountId)),
            ))),
            static fn(string $resource): never => throw MessageFailures::busy($resource),
        );
    }
}
