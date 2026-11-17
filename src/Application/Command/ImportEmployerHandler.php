<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command;

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\EmployerAccountView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception\EmployerAlreadyTracked;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\IdGenerator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\EmployerLocator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\RecordedEvents;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;

/**
 * Tracks an existing Pajemploi employer. Idempotent: the same external reference with the same employer returns the
 * tracked account; the same reference with another employer, or an employer tracked under another reference, is refused.
 */
final readonly class ImportEmployerHandler
{
    public function __construct(
        private EmployerLocator $locator,
        private EmployerAccountRepository $accounts,
        private MandateRepository $mandates,
        private IdGenerator $ids,
        private Clock $clock,
        private UnitOfWork $unitOfWork,
    ) {}

    /**
     * @throws EmployerAlreadyTracked
     */
    public function __invoke(ImportEmployer $command): EmployerAccountView
    {
        $existing = $this->locator->findExisting($command->tenantId, $command->externalReference, $command->employer);
        if ($existing instanceof EmployerAccount) {
            return EmployerAccountView::fromAccount($existing, $this->mandates->findActive($command->tenantId, $existing->employerNumber()));
        }

        $account = $this->unitOfWork->run(function (RecordedEvents $events) use ($command): EmployerAccount {
            $account = EmployerAccount::import(
                EmployerAccountId::fromString($this->ids->generate()),
                $command->tenantId,
                $command->externalReference,
                $command->employer,
                Instant::fromDateTime($this->clock->now()),
            );
            $this->accounts->save($account);
            $events->record(...$account->releaseEvents());

            return $account;
        });

        return EmployerAccountView::fromAccount($account, null);
    }
}
