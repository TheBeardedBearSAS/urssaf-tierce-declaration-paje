<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\PurgeOutboxHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RelayOutboxHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\EventPublisher;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\FailureReporter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\IdGenerator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\IntegrationEventSink;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Outbox;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\RateLimiter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\TokenStore;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\TransactionManager;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ChildrenVerifier;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ContributionEstimator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\EmployeeAssociator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\EmployeeVerifier;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\EmployerVerifier;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\MandateRegistrar;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\PajeGateway;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\SalaryDeclarer;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\SalaryPreDeclarer;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\IgnoredOutbox;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\DependencyInjection\ClockFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\DependencyInjection\GatewayOptionsFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\DependencyInjection\ServiceIds;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\DependencyInjection\SimulatedGatewayFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Outbox\MessengerIntegrationEventSink;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Credentials\StaticCredentialsProvider;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Event\Psr14EventPublisher;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Event\Psr14IntegrationEventSink;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Logging\PsrLoggerFailureReporter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Outbox\InMemoryOutbox;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\RateLimiter\NoopRateLimiter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\RateLimiter\SlidingWindowRateLimiter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\TokenStore\InMemoryTokenStore;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\TokenStore\Psr6TokenStore;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\GatewayOptions;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Http\JsonApiClient;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Http\OAuth2TokenProvider;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\HttpHeader;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\HttpTransport;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\UrssafHttpGateway;
use TheBeardedBear\UrssafTierceDeclarationPaje\Testing\SimulatedPajeGateway;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\App\TestKernel;

/**
 * The service definitions declared by the bundle (not the compiled container): arguments, aliases, visibility, tags.
 *
 * One test per definition of the bundle, hence the method count.
 *
 * @mago-expect lint:too-many-methods
 */
final class ServiceDefinitionsTest extends TestCase
{
    private const LOGGER_TAG = ['monolog.logger' => [['channel' => 'urssaf_paje']]];

    public function testThePortsOfTheGatewayAreAliasesOfTheGateway(): void
    {
        $container = self::definitions();

        foreach ([EmployerVerifier::class, EmployeeVerifier::class, ChildrenVerifier::class, EmployeeAssociator::class, MandateRegistrar::class, ContributionEstimator::class, SalaryPreDeclarer::class, SalaryDeclarer::class] as $port) {
            static::assertSame(PajeGateway::class, (string) $container->getAlias($port), $port);
            static::assertFalse($container->getAlias($port)->isPublic(), $port);
        }
        static::assertSame(UrssafHttpGateway::class, $container->getDefinition(PajeGateway::class)->getClass());
        static::assertFalse($container->getDefinition(PajeGateway::class)->isPublic());
    }

    public function testTheGatewayArgumentsAreThoseOfTheGatewayFactory(): void
    {
        $container = self::definitions();

        static::assertEquals([new Reference(ServiceIds::JSON_CLIENT), new Reference(ServiceIds::API_CONFIGURATION)], $container->getDefinition(PajeGateway::class)->getArguments());
        static::assertSame(JsonApiClient::class, $container->getDefinition(ServiceIds::JSON_CLIENT)->getClass());
        static::assertEquals(
            [
                new Reference(ServiceIds::HTTP_TRANSPORT),
                new Reference(ServiceIds::TOKEN_PROVIDER),
                new Reference(CredentialsProvider::class),
                new Reference(ServiceIds::API_CONFIGURATION),
                new Reference(ServiceIds::GATEWAY_OPTIONS),
            ],
            $container->getDefinition(ServiceIds::JSON_CLIENT)->getArguments(),
        );
        static::assertSame(HttpTransport::class, $container->getDefinition(ServiceIds::HTTP_TRANSPORT)->getClass());
    }

    public function testTheTokenProviderUsesTheSharedTokenStoreAndTheProbeHasItsOwn(): void
    {
        $container = self::definitions();
        $token = $container->getDefinition(ServiceIds::TOKEN_PROVIDER);
        $probe = $container->getDefinition(ServiceIds::PROBE_TOKEN_PROVIDER);

        static::assertSame(OAuth2TokenProvider::class, $token->getClass());
        static::assertSame(OAuth2TokenProvider::class, $probe->getClass());
        static::assertEquals(
            [new Reference(ServiceIds::HTTP_TRANSPORT), new Reference(TokenStore::class), new Reference(ServiceIds::API_CONFIGURATION), new Reference(ServiceIds::GATEWAY_OPTIONS)],
            $token->getArguments(),
        );
        $probeStore = self::nested($probe, 1);
        static::assertSame(InMemoryTokenStore::class, $probeStore->getClass());
        static::assertEquals(
            [new Reference(ServiceIds::HTTP_TRANSPORT), $probeStore, new Reference(ServiceIds::API_CONFIGURATION), new Reference(ServiceIds::GATEWAY_OPTIONS)],
            $probe->getArguments(),
        );
    }

    public function testTheDedicatedHttpClientIsUsedByDefault(): void
    {
        $container = self::definitions(['http_client' => null, 'http_timeout' => 7.5]);
        $transport = $container->getDefinition(ServiceIds::HTTP_TRANSPORT);

        static::assertEquals(
            [new Reference(ServiceIds::PSR18_CLIENT), new Reference(ServiceIds::PSR18_CLIENT), new Reference(ServiceIds::PSR18_CLIENT)],
            $transport->getArguments(),
        );
        static::assertSame([['timeout' => 7.5]], $container->getDefinition(ServiceIds::SYMFONY_HTTP_CLIENT)->getArguments());
        static::assertEquals([new Reference(ServiceIds::SYMFONY_HTTP_CLIENT)], $container->getDefinition(ServiceIds::PSR18_CLIENT)->getArguments());
    }

    public function testTheHttpClientOfTheApplicationReplacesTheDedicatedOneForTheCalls(): void
    {
        $transport = self::definitions()->getDefinition(ServiceIds::HTTP_TRANSPORT);

        static::assertEquals(
            [new Reference('test.http_client'), new Reference(ServiceIds::PSR18_CLIENT), new Reference(ServiceIds::PSR18_CLIENT)],
            $transport->getArguments(),
        );
    }

    public function testTheApiConfigurationArgumentsFollowTheConstructor(): void
    {
        $container = self::definitions([
            'environment' => 'production',
            'base_uri' => 'https://proxy.example.test',
            'user_agent' => 'my-application/1.0',
            'request_id_header' => 'X-Request-Id',
            'application_id_header' => ['name' => 'X-Application', 'value' => 'my-application'],
            'token_safety_margin' => 120,
            'retry_safe_writes' => ['030', '031'],
            'workflow' => ['business_timezone' => 'Pacific/Noumea'],
        ]);
        $arguments = $container->getDefinition(ServiceIds::API_CONFIGURATION)->getArguments();

        static::assertSame(ApiConfiguration::class, $container->getDefinition(ServiceIds::API_CONFIGURATION)->getClass());
        static::assertCount(8, $arguments);
        $environment = self::nested($container->getDefinition(ServiceIds::API_CONFIGURATION), 0);
        static::assertSame([Environment::class, 'from'], $environment->getFactory());
        static::assertSame(['production'], $environment->getArguments());
        static::assertSame('https://proxy.example.test', $arguments[1]);
        static::assertSame('X-Request-Id', $arguments[2]);
        $application = self::nested($container->getDefinition(ServiceIds::API_CONFIGURATION), 3);
        static::assertSame([HttpHeader::class, 'of'], $application->getFactory());
        static::assertSame(['X-Application', 'my-application'], $application->getArguments());
        static::assertSame(120, $arguments[4]);
        static::assertSame('my-application/1.0', $arguments[5]);
        $zone = self::nested($container->getDefinition(ServiceIds::API_CONFIGURATION), 6);
        static::assertSame(['Pacific/Noumea'], $zone->getArguments());
        $writes = self::nestedList($container->getDefinition(ServiceIds::API_CONFIGURATION), 7);
        static::assertCount(2, $writes);
        foreach ([Operation::RegisterMandate, Operation::CancelMandate] as $index => $operation) {
            static::assertEquals((new Definition(Operation::class, [$operation->value]))->setFactory([Operation::class, 'from']), $writes[$index]);
        }
    }

    public function testTheApiConfigurationWithoutOptionalHeadersHasNone(): void
    {
        $arguments = self::definitions(['application_id_header' => ['name' => '', 'value' => '']])->getDefinition(ServiceIds::API_CONFIGURATION)->getArguments();

        static::assertNull($arguments[2]);
        static::assertNull($arguments[3]);
        static::assertSame([], $arguments[7]);
    }

    public function testTheGatewayOptionsAreBuiltByTheFactoryAndLogOnTheUrssafPajeChannel(): void
    {
        $definition = self::definitions()->getDefinition(ServiceIds::GATEWAY_OPTIONS);

        static::assertSame(GatewayOptions::class, $definition->getClass());
        static::assertSame([GatewayOptionsFactory::class, 'create'], $definition->getFactory());
        static::assertEquals(
            [new Reference(Clock::class), new Reference(IdGenerator::class), new Reference(RateLimiter::class), new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE)],
            $definition->getArguments(),
        );
        static::assertSame(self::LOGGER_TAG, $definition->getTags());
    }

    public function testTheFailureReporterReceivesTheOptionalPackageLogger(): void
    {
        $definition = self::definitions()->getDefinition(FailureReporter::class);

        static::assertSame(PsrLoggerFailureReporter::class, $definition->getClass());
        static::assertEquals([new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)], $definition->getArguments());
        static::assertSame(self::LOGGER_TAG, $definition->getTags());
    }

    public function testTheEventsAndTheIdentifiersAreWiredOnTheContainer(): void
    {
        $container = self::definitions();

        static::assertSame(Psr14EventPublisher::class, $container->getDefinition(EventPublisher::class)->getClass());
        static::assertEquals([new Reference('event_dispatcher')], $container->getDefinition(EventPublisher::class)->getArguments());
        static::assertSame([ClockFactory::class, 'create'], $container->getDefinition(Clock::class)->getFactory());
        static::assertEquals([new Reference('clock', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(Clock::class)->getArguments());
    }

    public function testTheCredentialsAreBuiltFromTheConfiguration(): void
    {
        $definition = self::definitions()->getDefinition(CredentialsProvider::class);
        $tenants = self::nestedList($definition, 0);

        static::assertSame(StaticCredentialsProvider::class, $definition->getClass());
        static::assertSame(['tenant-1'], array_keys($tenants));
        // @mago-expect analysis:mixed-assignment The tenants map is untyped; the assertion below narrows it.
        $credentials = $tenants['tenant-1'];
        static::assertInstanceOf(Definition::class, $credentials);
        // The secret is an environment placeholder: the value never reaches the definitions.
        static::assertSame('client-1', $credentials->getArgument(0));
        static::assertStringContainsString('URSSAF_TEST_SECRET', self::text($credentials, 1));
        static::assertStringNotContainsString(TestKernel::SECRET, self::text($credentials, 1));
    }

    public function testTheCredentialsProviderOfTheApplicationIsAnAlias(): void
    {
        $container = self::definitions(['credentials' => null, 'credentials_provider' => 'test.credentials']);

        static::assertSame('test.credentials', (string) $container->getAlias(CredentialsProvider::class));
    }

    public function testTheTokenStoreIsAPsr6StoreOnTheConfiguredPool(): void
    {
        $definition = self::definitions(['token_store' => ['cache_pool' => 'my.pool']])->getDefinition(TokenStore::class);

        static::assertSame(Psr6TokenStore::class, $definition->getClass());
        static::assertEquals([new Reference('my.pool')], $definition->getArguments());
    }

    public function testTheTokenStoreOfTheApplicationIsAnAlias(): void
    {
        static::assertSame('test.token_store', (string) self::definitions(['token_store' => ['service' => 'test.token_store']])->getAlias(TokenStore::class));
    }

    public function testTheRateLimiterIsOffByDefaultAndTakesTheHostLimitsWhenEnabled(): void
    {
        static::assertSame(NoopRateLimiter::class, self::definitions()->getDefinition(RateLimiter::class)->getClass());

        $limiter = self::definitions(['rate_limiter' => ['enabled' => true, 'limit' => 100, 'window' => 60]])->getDefinition(RateLimiter::class);

        static::assertSame(SlidingWindowRateLimiter::class, $limiter->getClass());
        static::assertEquals([100, 60, new Reference(Clock::class)], $limiter->getArguments());
    }

    public function testTheWorkflowPolicyReceivesNamedArguments(): void
    {
        $definition = self::definitions([
            'outbox' => ['retention' => 'P7D'],
            'workflow' => [
                'business_timezone' => 'Pacific/Noumea',
                'stale_submission_after' => 'PT45M',
                'max_submission_attempts' => 3,
                'require_local_mandate' => true,
                'predeclare_before_declaring' => true,
                'default_page_size' => 10,
                'max_page_size' => 20,
                'erasure' => ['allow_unknown_outcomes' => true, 'allow_active_mandate' => true],
            ],
        ])->getDefinition(WorkflowPolicy::class);
        $arguments = $definition->getArguments();

        static::assertSame(
            ['$businessTimeZone', '$staleSubmissionAfter', '$maxSubmissionAttempts', '$requireLocalMandate', '$preDeclareBeforeDeclaring', '$defaultPageSize', '$maxPageSize', '$allowErasureWithUnknownOutcomes', '$allowErasureWithActiveMandate', '$outboxRetention'],
            array_keys($arguments),
        );
        static::assertSame(3, $arguments['$maxSubmissionAttempts']);
        static::assertTrue($arguments['$requireLocalMandate']);
        static::assertTrue($arguments['$preDeclareBeforeDeclaring']);
        static::assertSame(10, $arguments['$defaultPageSize']);
        static::assertSame(20, $arguments['$maxPageSize']);
        static::assertTrue($arguments['$allowErasureWithUnknownOutcomes']);
        static::assertTrue($arguments['$allowErasureWithActiveMandate']);
        foreach (['$businessTimeZone' => 'Pacific/Noumea', '$staleSubmissionAfter' => 'PT45M', '$outboxRetention' => 'P7D'] as $name => $value) {
            static::assertInstanceOf(Definition::class, $arguments[$name]);
            static::assertSame([$value], $arguments[$name]->getArguments());
        }
    }

    public function testTheUnitOfWorkReceivesEveryCollaboratorExplicitly(): void
    {
        $definition = self::definitions()->getDefinition(UnitOfWork::class);

        static::assertEquals(
            [new Reference(TransactionManager::class), new Reference(EventPublisher::class), new Reference(FailureReporter::class), new Reference(Outbox::class)],
            $definition->getArguments(),
        );
        static::assertFalse($definition->isAutowired());
    }

    public function testTheOutboxIsIgnoredWhenDisabled(): void
    {
        $container = self::definitions();

        static::assertSame(IgnoredOutbox::class, $container->getDefinition(Outbox::class)->getClass());
        static::assertFalse($container->hasDefinition(IntegrationEventSink::class));
        static::assertFalse($container->hasDefinition(RelayOutboxHandler::class));
        static::assertFalse($container->hasDefinition(PurgeOutboxHandler::class));
    }

    public function testTheOutboxIsInMemoryByDefaultAndRelaysToMessenger(): void
    {
        $container = self::definitions(['outbox' => ['enabled' => true, 'bus' => 'my.bus']]);

        static::assertSame(InMemoryOutbox::class, $container->getDefinition(Outbox::class)->getClass());
        static::assertSame(MessengerIntegrationEventSink::class, $container->getDefinition(IntegrationEventSink::class)->getClass());
        static::assertEquals([new Reference('my.bus')], $container->getDefinition(IntegrationEventSink::class)->getArguments());
        static::assertTrue($container->getDefinition(RelayOutboxHandler::class)->isPublic());
        static::assertTrue($container->getDefinition(PurgeOutboxHandler::class)->isPublic());
    }

    public function testTheOutboxOfTheApplicationIsAnAlias(): void
    {
        static::assertSame('test.outbox', (string) self::definitions(['outbox' => ['enabled' => true, 'service' => 'test.outbox']])->getAlias(Outbox::class));
    }

    public function testTheSinkMayBeTheEventDispatcher(): void
    {
        $sink = self::definitions(['outbox' => ['enabled' => true, 'sink' => 'event_dispatcher']])->getDefinition(IntegrationEventSink::class);

        static::assertSame(Psr14IntegrationEventSink::class, $sink->getClass());
        static::assertEquals([new Reference('event_dispatcher')], $sink->getArguments());
    }

    public function testTheSinkMayBeAServiceOfTheApplication(): void
    {
        static::assertSame('test.sink', (string) self::definitions(['outbox' => ['enabled' => true, 'sink' => 'test.sink']])->getAlias(IntegrationEventSink::class));
    }

    public function testTheLocksReceiveTheOptionalFactoryAndTheTtl(): void
    {
        static::assertEquals([new Reference('lock.factory', ContainerInterface::NULL_ON_INVALID_REFERENCE), 3600.0], self::definitions()->getDefinition(OperationLocks::class)->getArguments());
        static::assertEquals([new Reference('lock.factory'), 30.0], self::definitions(['lock' => ['enabled' => true, 'ttl' => 30.0]])->getDefinition(OperationLocks::class)->getArguments());
        static::assertEquals([null, 3600.0], self::definitions(['lock' => ['enabled' => false]])->getDefinition(OperationLocks::class)->getArguments());
    }

    public function testTheSimulatedGatewayIsBuiltByTheFactoryAndPublic(): void
    {
        $container = self::definitions(['gateway' => ['simulated' => true]]);

        static::assertSame(SimulatedPajeGateway::class, $container->getDefinition(PajeGateway::class)->getClass());
        static::assertSame([SimulatedGatewayFactory::class, 'create'], $container->getDefinition(PajeGateway::class)->getFactory());
        static::assertSame(['sandbox'], $container->getDefinition(PajeGateway::class)->getArguments());
        static::assertSame(PajeGateway::class, (string) $container->getAlias(SimulatedPajeGateway::class));
        static::assertTrue($container->getAlias(SimulatedPajeGateway::class)->isPublic());
    }

    private static function nested(Definition $definition, int $index): Definition
    {
        // @mago-expect analysis:mixed-assignment The arguments of a definition are untyped; the assertion below narrows them.
        $nested = $definition->getArguments()[$index] ?? null;
        static::assertInstanceOf(Definition::class, $nested);

        return $nested;
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function nestedList(Definition $definition, int $index): array
    {
        // @mago-expect analysis:mixed-assignment The arguments of a definition are untyped; the assertion below narrows them.
        $list = $definition->getArguments()[$index] ?? null;
        static::assertIsArray($list);

        return $list;
    }

    private static function text(Definition $definition, int $index): string
    {
        // @mago-expect analysis:mixed-assignment The arguments of a definition are untyped; the assertion below narrows them.
        $text = $definition->getArguments()[$index] ?? null;
        static::assertIsString($text);

        return $text;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function definitions(array $config = []): ContainerBuilder
    {
        return (new TestKernel($config))->definitions();
    }
}
