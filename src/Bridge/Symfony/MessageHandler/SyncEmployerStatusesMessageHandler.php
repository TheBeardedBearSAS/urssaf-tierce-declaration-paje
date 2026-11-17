<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\MessageHandler;

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\SyncEmployerStatuses;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\SyncEmployerStatusesHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Message\SyncEmployerStatusesMessage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Refreshes the least recently checked employers of a tenant (method 020), under the lock of the operation. The batch
 * report is discarded: a failed account is left untouched by the use case and retried by the next run.
 */
final readonly class SyncEmployerStatusesMessageHandler
{
    public function __construct(private SyncEmployerStatusesHandler $sync, private OperationLocks $locks) {}

    public function __invoke(SyncEmployerStatusesMessage $message): void
    {
        $this->locks->exclusively(
            'employers.sync-status',
            $message->tenantId,
            fn(): mixed => MessageFailures::guard(fn(): mixed => ($this->sync)(new SyncEmployerStatuses(TenantId::fromString($message->tenantId), null))),
            static fn(string $resource): never => throw MessageFailures::busy($resource),
        );
    }
}
