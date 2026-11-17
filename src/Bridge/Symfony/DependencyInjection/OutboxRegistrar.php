<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\DependencyInjection;

use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\PurgeOutboxHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RelayOutboxHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\IntegrationEventSink;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Outbox;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\IgnoredOutbox;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Outbox\MessengerIntegrationEventSink;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Event\Psr14IntegrationEventSink;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Outbox\InMemoryOutbox;

/**
 * The outbox (written by the UnitOfWork), its sink, relay and purge. The {@see Outbox} port is always defined: the
 * {@see IgnoredOutbox} when the outbox is disabled, so that the unit of work never falls back silently.
 *
 * @phpstan-import-type Config from ServiceRegistrar
 */
final readonly class OutboxRegistrar
{
    public const SINK_MESSENGER = 'messenger';
    public const SINK_EVENT_DISPATCHER = 'event_dispatcher';

    public function __construct(private ServicesConfigurator $services) {}

    /**
     * @param Config $config
     */
    public function register(array $config): void
    {
        $outbox = $config['outbox'];
        if (!$outbox['enabled']) {
            $this->services->set(Outbox::class, IgnoredOutbox::class);

            return;
        }
        $this->registerOutbox($outbox['service']);
        $this->registerSink($outbox['sink'], $outbox['bus']);
        $this->services->set(RelayOutboxHandler::class)->autowire()->public();
        $this->services->set(PurgeOutboxHandler::class)->autowire()->public();
    }

    /**
     * Without "service", the in-memory outbox (the "custom" driver cannot reach it: it requires "outbox.service").
     */
    private function registerOutbox(?string $service): void
    {
        if ($service !== null) {
            $this->services->alias(Outbox::class, $service);

            return;
        }
        $this->services->set(Outbox::class, InMemoryOutbox::class);
    }

    private function registerSink(string $sink, string $bus): void
    {
        if ($sink === self::SINK_MESSENGER) {
            $this->services->set(IntegrationEventSink::class, MessengerIntegrationEventSink::class)->args([new Reference($bus)]);

            return;
        }
        if ($sink === self::SINK_EVENT_DISPATCHER) {
            $this->services->set(IntegrationEventSink::class, Psr14IntegrationEventSink::class)->args([new Reference('event_dispatcher')]);

            return;
        }
        $this->services->alias(IntegrationEventSink::class, $sink);
    }
}
