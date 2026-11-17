<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\DependencyInjection;

use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\MessageHandler\SyncEmployerStatusesMessageHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\MessageHandler\SyncEmployerStatusMessageHandler;

/**
 * The Messenger handlers of the employer refresh messages (the only Messenger scope of the package, ADR 0013, decision 2:
 * no declaration, 023, 030 nor 031 message, no auto-submit listener).
 */
final readonly class MessengerRegistrar
{
    private const MESSAGE_HANDLERS = [
        SyncEmployerStatusesMessageHandler::class,
        SyncEmployerStatusMessageHandler::class,
    ];

    public function __construct(private ServicesConfigurator $services) {}

    public function register(): void
    {
        foreach (self::MESSAGE_HANDLERS as $handler) {
            $this->services->set($handler)->autowire()->tag('messenger.message_handler');
        }
    }
}
