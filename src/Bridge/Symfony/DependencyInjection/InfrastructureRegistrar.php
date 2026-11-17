<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Credentials;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\EventPublisher;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\FailureReporter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\IdGenerator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\RateLimiter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\TokenStore;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Credentials\StaticCredentialsProvider;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Event\Psr14EventPublisher;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Id\RandomUuidGenerator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Id\SecureRandomBytes;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Logging\PsrLoggerFailureReporter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\RateLimiter\NoopRateLimiter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\RateLimiter\SlidingWindowRateLimiter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\TokenStore\Psr6TokenStore;

/**
 * Generic infrastructure: clock, identifiers, events, failure reporting, credentials, access token cache, rate limiter.
 *
 * @phpstan-import-type Config from ServiceRegistrar
 */
final readonly class InfrastructureRegistrar
{
    private const LOGGER_TAG = 'monolog.logger';

    public function __construct(private ServicesConfigurator $services) {}

    /**
     * @param Config $config
     */
    public function register(array $config): void
    {
        $this->services->set(Clock::class, Clock::class)
            ->factory(ClockFactory::class . '::create')
            ->args([new Reference('clock', ContainerInterface::NULL_ON_INVALID_REFERENCE)]);
        $this->services->set(IdGenerator::class, RandomUuidGenerator::class)->args([new Definition(SecureRandomBytes::class)]);
        $this->services->set(EventPublisher::class, Psr14EventPublisher::class)->args([new Reference('event_dispatcher')]);
        $this->services->set(FailureReporter::class, PsrLoggerFailureReporter::class)
            ->args([new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)])
            ->tag(self::LOGGER_TAG, ['channel' => ServiceIds::LOGGER_CHANNEL]);
        $this->registerCredentials($config['credentials_provider'], $config['credentials']);
        $this->registerTokenStore($config['token_store']);
        $this->registerRateLimiter($config['rate_limiter']);
    }

    /**
     * @param array<string, array{client_id: string, client_secret: string}> $credentials
     */
    private function registerCredentials(?string $provider, array $credentials): void
    {
        if ($provider !== null) {
            $this->services->alias(CredentialsProvider::class, $provider);

            return;
        }
        $definitions = [];
        foreach ($credentials as $tenantId => $tenant) {
            $definitions[$tenantId] = (new Definition(Credentials::class, [$tenant['client_id'], $tenant['client_secret']]))
                ->setFactory([Credentials::class, 'of']);
        }
        $this->services->set(CredentialsProvider::class, StaticCredentialsProvider::class)->args([$definitions]);
    }

    /**
     * @param array{cache_pool: string, service: ?string} $tokenStore
     */
    private function registerTokenStore(array $tokenStore): void
    {
        if ($tokenStore['service'] !== null) {
            $this->services->alias(TokenStore::class, $tokenStore['service']);

            return;
        }
        $this->services->set(TokenStore::class, Psr6TokenStore::class)->args([new Reference($tokenStore['cache_pool'])]);
    }

    /**
     * @param array{enabled: bool, limit: ?int, window: ?int} $rateLimiter
     */
    private function registerRateLimiter(array $rateLimiter): void
    {
        if ($rateLimiter['enabled']) {
            $this->services->set(RateLimiter::class, SlidingWindowRateLimiter::class)
                ->args([$rateLimiter['limit'], $rateLimiter['window'], new Reference(Clock::class)]);

            return;
        }
        $this->services->set(RateLimiter::class, NoopRateLimiter::class);
    }
}
