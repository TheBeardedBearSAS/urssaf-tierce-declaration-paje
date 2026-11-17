<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake;

use Override;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Outbox;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\OutboxEntry;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\IntegrationEvent;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;

/**
 * Outbox that writes each appended event type in the journal (to check that it happens inside the transaction) and
 * keeps the appended events.
 */
final class JournalingOutbox implements Outbox
{
    /** @var list<IntegrationEvent> */
    private array $appended = [];

    public function __construct(private readonly Journal $journal) {}

    #[Override]
    public function append(IntegrationEvent ...$events): void
    {
        $this->appended = [...$this->appended, ...array_values($events)];
        $this->journal->add('append:' . implode(',', array_map(static fn(IntegrationEvent $event): string => $event->type(), $events)));
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

    /**
     * @return list<IntegrationEvent>
     */
    public function appended(): array
    {
        return $this->appended;
    }
}
