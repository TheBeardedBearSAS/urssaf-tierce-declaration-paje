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

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\RequeueReport;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\RecordedEvents;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;

/**
 * Once the tenant credentials are fixed: moves up to "limit" declarations from invalid_credentials back to pending
 * (one transaction). The host then declares them again with DeclareSalary (it holds the payload, ADR 0007). Run it
 * again while the returned count reaches the limit.
 *
 * A record another process changed since it was read ("declaration_record.concurrent_modification") makes the whole
 * transaction fail and nothing is requeued: run it again, it reads the records again.
 */
final readonly class RequeueAfterInvalidCredentialsHandler
{
    public function __construct(
        private DeclarationRecordRepository $records,
        private Clock $clock,
        private UnitOfWork $unitOfWork,
        private WorkflowPolicy $policy,
    ) {}

    /**
     * @throws InvariantViolation "declaration_record.concurrent_modification" (nothing was requeued)
     */
    public function __invoke(RequeueAfterInvalidCredentials $command): RequeueReport
    {
        $declarations = $this->records->findByState(
            $command->tenantId,
            DeclarationState::InvalidCredentials,
            $this->policy->pageSize($command->limit),
        );

        return $this->unitOfWork->run(function (RecordedEvents $events) use ($declarations): RequeueReport {
            $now = Instant::fromDateTime($this->clock->now());
            foreach ($declarations as $declaration) {
                $declaration->retry($now, $this->policy->maxSubmissionAttempts());
                $this->records->save($declaration);
                $events->record(...$declaration->releaseEvents());
            }

            return new RequeueReport(\count($declarations));
        });
    }
}
