<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Outbox;

use Override;
use Symfony\Component\Messenger\MessageBusInterface;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\IntegrationEventSink;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\IntegrationEvent;

/**
 * Delivers the integration events of the outbox to a Messenger bus (route them in the host application): a failing
 * dispatch keeps the event in the outbox, it is relayed again later (ADR 0012).
 */
final readonly class MessengerIntegrationEventSink implements IntegrationEventSink
{
    public function __construct(private MessageBusInterface $bus) {}

    #[Override]
    public function dispatch(IntegrationEvent $event): void
    {
        $this->bus->dispatch($event);
    }
}
