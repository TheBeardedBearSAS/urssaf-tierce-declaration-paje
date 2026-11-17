<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Contract;

use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Credentials;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\PajeGateway;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Credentials\StaticCredentialsProvider;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\RateLimiter\NoopRateLimiter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\TokenStore\InMemoryTokenStore;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\GatewayOptions;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\HttpTransport;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\UrssafEndpoints;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\UrssafGatewayFactory;

/**
 * Wires the real HTTP gateway against the URSSAF sandbox from the URSSAF_SANDBOX_* variables. One instance is shared by
 * the whole run (one HTTP client, one token store, therefore one token and one recording of every exchange). No write
 * is declared safe to retry (the default of the SDK): the retry behaviour of 023, 030 and 031 is observed with an
 * explicit second call.
 */
final class SandboxGateway
{
    public const TENANT = 'contract-tests';

    private static self|false|null $shared = null;

    private function __construct(
        public readonly PajeGateway $gateway,
        public readonly SandboxHttpClient $http,
        public readonly TenantId $tenantId,
        public readonly ApiConfiguration $configuration,
        public readonly ?string $tokenUriOverride,
        private readonly Credentials $credentials,
        private readonly Psr17Factory $factory,
    ) {}

    /**
     * Null without credentials (checked first: a misconfigured base URI without credentials skips, it does not fail).
     *
     * @throws SandboxMisconfigured
     */
    public static function fromEnvironment(SandboxEnvironment $env): ?self
    {
        if (!$env->hasCredentials()) {
            return null;
        }

        $baseUri = $env->baseUri();
        $tokenUri = $env->tokenUri();
        $credentials = $env->credentials();
        $configuration = new ApiConfiguration(Environment::Sandbox, $baseUri);

        $factory = new Psr17Factory();
        $http = new SandboxHttpClient(new Psr18Client(HttpClient::create(['timeout' => 30]), $factory, $factory), $tokenUri);
        $transport = new HttpTransport($http, $factory, $factory);
        $options = GatewayOptions::defaults()->withRateLimiter(new NoopRateLimiter());

        SandboxReport::describeRun(
            (string) parse_url($configuration->baseUri(), \PHP_URL_HOST),
            (string) parse_url($tokenUri ?? $configuration->tokenUri(), \PHP_URL_HOST),
            $env->writesAllowed(),
        );

        return new self(
            UrssafGatewayFactory::create(
                $transport,
                new StaticCredentialsProvider([self::TENANT => $credentials]),
                new InMemoryTokenStore(),
                $configuration,
                $options,
            ),
            $http,
            TenantId::fromString(self::TENANT),
            $configuration,
            $tokenUri,
            $credentials,
            $factory,
        );
    }

    /**
     * The process-wide instance, null without credentials.
     *
     * @throws SandboxMisconfigured
     */
    public static function shared(): ?self
    {
        self::$shared ??= self::fromEnvironment(SandboxEnvironment::fromProcess()) ?? false;

        return self::$shared instanceof self ? self::$shared : null;
    }

    /**
     * One token request, same form as the SDK sends, straight to the given URI (no rewrite). The token itself is never
     * kept: only the shape of the response is recorded.
     */
    public function rawTokenProbe(string $uri): Exchange
    {
        $body = http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => $this->credentials->clientId(),
            'client_secret' => $this->credentials->clientSecret(),
            'scope' => UrssafEndpoints::TOKEN_SCOPE,
        ]);
        $request = $this->factory->createRequest('POST', $uri)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withHeader('Accept', 'application/json')
            ->withBody($this->factory->createStream($body));

        return $this->http->probe($request);
    }
}
