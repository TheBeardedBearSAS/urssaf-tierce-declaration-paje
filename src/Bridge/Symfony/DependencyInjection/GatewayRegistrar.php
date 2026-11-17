<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\DependencyInjection;

use DateTimeZone;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\IdGenerator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\RateLimiter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\TokenStore;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ChildrenVerifier;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ContributionEstimator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\EmployeeAssociator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\EmployeeVerifier;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\EmployerVerifier;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\MandateRegistrar;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\PajeGateway;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\SalaryDeclarer;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\SalaryPreDeclarer;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\TokenStore\InMemoryTokenStore;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\GatewayOptions;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Http\JsonApiClient;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Http\OAuth2TokenProvider;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\HttpHeader;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\HttpTransport;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\UrssafHttpGateway;
use TheBeardedBear\UrssafTierceDeclarationPaje\Testing\SimulatedPajeGateway;

/**
 * The URSSAF HTTP gateway and everything it needs: PSR-18 client, PSR-17 factories, access tokens, JSON client.
 *
 * It is wired piece by piece (not through UrssafGatewayFactory) to expose the probe token provider of
 * "urssaf:paje:check"; the arguments are those of UrssafGatewayFactory::create().
 *
 * @phpstan-import-type Config from ServiceRegistrar
 */
final readonly class GatewayRegistrar
{
    private const LOGGER_TAG = 'monolog.logger';

    /** Ports implemented by the gateway, each one an alias of {@see PajeGateway}. */
    private const PORTS = [
        EmployerVerifier::class,
        EmployeeVerifier::class,
        ChildrenVerifier::class,
        EmployeeAssociator::class,
        MandateRegistrar::class,
        ContributionEstimator::class,
        SalaryPreDeclarer::class,
        SalaryDeclarer::class,
    ];

    public function __construct(private ServicesConfigurator $services) {}

    /**
     * @param Config $config
     */
    public function register(array $config): void
    {
        $this->services->set(ServiceIds::SYMFONY_HTTP_CLIENT, HttpClientInterface::class)
            ->factory(HttpClient::class . '::create')
            ->args([['timeout' => $config['http_timeout']]]);
        // Also provides the PSR-17 request and stream factories, whatever the PSR-18 client in use.
        $this->services->set(ServiceIds::PSR18_CLIENT, Psr18Client::class)->args([new Reference(ServiceIds::SYMFONY_HTTP_CLIENT)]);
        $this->services->set(ServiceIds::HTTP_TRANSPORT, HttpTransport::class)->args([
            new Reference($config['http_client'] ?? ServiceIds::PSR18_CLIENT),
            new Reference(ServiceIds::PSR18_CLIENT),
            new Reference(ServiceIds::PSR18_CLIENT),
        ]);
        $this->services->set(ServiceIds::API_CONFIGURATION, ApiConfiguration::class)->args([
            // Resolved at runtime: the environment is usually an "%env(URSSAF_PAJE_ENV)%" placeholder.
            (new Definition(Environment::class, [$config['environment']]))->setFactory([Environment::class, 'from']),
            $config['base_uri'],
            $config['request_id_header'],
            $this->applicationIdHeader($config['application_id_header']),
            $config['token_safety_margin'],
            $config['user_agent'],
            new Definition(DateTimeZone::class, [$config['workflow']['business_timezone']]),
            array_map(
                static fn(string $operation): Definition => (new Definition(Operation::class, [$operation]))->setFactory([Operation::class, 'from']),
                $config['retry_safe_writes'],
            ),
        ]);
        $this->services->set(ServiceIds::GATEWAY_OPTIONS, GatewayOptions::class)
            ->factory(GatewayOptionsFactory::class . '::create')
            ->args([
                new Reference(Clock::class),
                new Reference(IdGenerator::class),
                new Reference(RateLimiter::class),
                new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->tag(self::LOGGER_TAG, ['channel' => ServiceIds::LOGGER_CHANNEL]);

        foreach ([ServiceIds::TOKEN_PROVIDER => new Reference(TokenStore::class), ServiceIds::PROBE_TOKEN_PROVIDER => new Definition(InMemoryTokenStore::class)] as $id => $tokenStore) {
            $this->services->set($id, OAuth2TokenProvider::class)->args([
                new Reference(ServiceIds::HTTP_TRANSPORT),
                $tokenStore,
                new Reference(ServiceIds::API_CONFIGURATION),
                new Reference(ServiceIds::GATEWAY_OPTIONS),
            ]);
        }
        $this->services->set(ServiceIds::JSON_CLIENT, JsonApiClient::class)->args([
            new Reference(ServiceIds::HTTP_TRANSPORT),
            new Reference(ServiceIds::TOKEN_PROVIDER),
            new Reference(CredentialsProvider::class),
            new Reference(ServiceIds::API_CONFIGURATION),
            new Reference(ServiceIds::GATEWAY_OPTIONS),
        ]);
        $this->registerGateway($config);
        foreach (self::PORTS as $port) {
            $this->services->alias($port, PajeGateway::class);
        }
    }

    /**
     * The HTTP gateway, or the simulator (tests) with "gateway.simulated".
     *
     * @param Config $config
     */
    private function registerGateway(array $config): void
    {
        if ($config['gateway']['simulated']) {
            $this->services->set(PajeGateway::class, SimulatedPajeGateway::class)
                ->factory(SimulatedGatewayFactory::class . '::create')
                ->args([$config['environment']]);
            $this->services->alias(SimulatedPajeGateway::class, PajeGateway::class)->public();

            return;
        }
        $this->services->set(PajeGateway::class, UrssafHttpGateway::class)->args([
            new Reference(ServiceIds::JSON_CLIENT),
            new Reference(ServiceIds::API_CONFIGURATION),
        ]);
    }

    /**
     * An empty name is "not configured", like null (ConfigurationRules::incompleteApplicationIdHeader()).
     *
     * @param array{name: ?string, value: ?string} $header
     */
    private function applicationIdHeader(array $header): ?Definition
    {
        if ($header['name'] === null || $header['name'] === '') {
            return null;
        }

        return (new Definition(HttpHeader::class, [$header['name'], $header['value']]))->setFactory([HttpHeader::class, 'of']);
    }
}
