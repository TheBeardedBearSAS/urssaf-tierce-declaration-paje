<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake;

use Override;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Outbox;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\OutboxEntry;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\IntegrationEvent;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use Throwable;

/**
 * Outbox whose append always fails, like a database that goes away at the end of the transaction (after the aggregates
 * were saved).
 */
final readonly class FailingOutbox implements Outbox
{
    public function __construct(private Throwable $failure) {}

    #[Override]
    public function append(IntegrationEvent ...$events): never
    {
        throw $this->failure;
    }

    #[Override]
    public function pending(int $limit): array
    {
        return [];
    }

    #[Override]
    public function markDispatched(OutboxEntry $entry, Instant $at): void {}

    #[Override]
    public function markFailed(OutboxEntry $entry, string $error): void {}

    #[Override]
    public function purgeDispatchedBefore(Instant $before): int
    {
        return 0;
    }
}
