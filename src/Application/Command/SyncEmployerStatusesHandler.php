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

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\BatchItem;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\BatchReport;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\EmployerStatusRefresher;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RetryableException;

/**
 * Polls method 020 for the least recently checked employers of a tenant, never checked first, at most "limit" in total.
 * Per account (each one in its own transaction):
 * - answer applied: {@see ItemOutcome::Changed} when the snapshot changed, {@see ItemOutcome::Unchanged} otherwise;
 * - request refused by URSSAF: {@see ItemOutcome::Failed} with the error codes, the account is untouched;
 * - temporary failure ({@see RetryableException}: 429, transport, 5xx, token, local rate limit): {@see ItemOutcome::RetryLater},
 *   the account is untouched and the run goes on (020 is a safe read).
 * - account changed by another process since it was read ("employer_account.concurrent_modification"): the answer is
 *   discarded, {@see ItemOutcome::RetryLater}, the account is untouched and the run goes on.
 * Credential failures ({@see UrssafAuthenticationFailed}) concern the whole tenant and stop the run: what was already
 * processed stays committed.
 */
final readonly class SyncEmployerStatusesHandler
{
    public function __construct(
        private EmployerAccountRepository $accounts,
        private EmployerStatusRefresher $refresher,
        private WorkflowPolicy $policy,
    ) {}

    /**
     * @throws UrssafAuthenticationFailed
     */
    public function __invoke(SyncEmployerStatuses $command): BatchReport
    {
        $accounts = $this->accounts->findLeastRecentlyChecked($command->tenantId, $this->policy->pageSize($command->limit));

        $items = [];
        foreach ($accounts as $account) {
            $items[] = $this->sync($account);
        }

        return BatchReport::of(...$items);
    }

    /**
     * @throws UrssafAuthenticationFailed
     * @throws InvariantViolation         a repository refusal other than a concurrent modification
     */
    private function sync(EmployerAccount $account): BatchItem
    {
        $subject = $account->id()->value();

        try {
            $changed = $this->refresher->refresh($account);
        } catch (UrssafRequestRejected $rejection) {
            return new BatchItem($subject, ItemOutcome::Failed, $rejection->errors()->codes());
        } catch (RetryableException) {
            return new BatchItem($subject, ItemOutcome::RetryLater);
        } catch (InvariantViolation $violation) {
            if ($violation->violationCode() !== EmployerAccountRepository::CONCURRENT_MODIFICATION) {
                throw $violation;
            }

            return new BatchItem($subject, ItemOutcome::RetryLater, [$violation->violationCode()]);
        }

        return new BatchItem($subject, $changed ? ItemOutcome::Changed : ItemOutcome::Unchanged);
    }
}
