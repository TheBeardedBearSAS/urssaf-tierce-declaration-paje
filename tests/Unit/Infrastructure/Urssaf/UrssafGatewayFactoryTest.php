<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf;

use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\TokenStore\InMemoryTokenStore;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\UrssafGatewayFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\UrssafHttpGateway;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Fake\RejectingRateLimiter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\EmployeeWireSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Stack;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;

final class UrssafGatewayFactoryTest extends TestCase
{
    private static function stackWithEmptyAnswers(): Stack
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->http->setDefaultResponse(new Response(200, [], ''));

        return $stack;
    }

    public function testCreateWorksEndToEndWithTheDefaults(): void
    {
        $stack = self::stackWithEmptyAnswers();
        $gateway = UrssafGatewayFactory::create(
            $stack->transport(),
            $stack->credentials,
            new InMemoryTokenStore(),
            ApiConfiguration::forEnvironment(Environment::Sandbox),
        );

        $gateway->registerMandate(Stacks::tenant(), EmployeeWireSamples::employer());

        static::assertInstanceOf(UrssafHttpGateway::class, $gateway);
        $requests = $stack->requests();
        static::assertCount(2, $requests);
        static::assertSame('https://api-edi.urssaf.fr/api/oauth/v1/token', (string) $requests[0]->getUri());
        static::assertSame('POST', $requests[1]->getMethod());
        static::assertSame('/tiersdecl/v1/paje/mandats', $requests[1]->getUri()->getPath());
        static::assertStringStartsWith('Bearer ', $requests[1]->getHeaderLine('Authorization'));
        $headers = array_keys($requests[1]->getHeaders());
        sort($headers);
        static::assertSame(['Accept', 'Authorization', 'Host', 'User-Agent'], $headers);
    }

    public function testTheDefaultsDoNotThrottleLocally(): void
    {
        $stack = self::stackWithEmptyAnswers();
        $gateway = UrssafGatewayFactory::create(
            $stack->transport(),
            $stack->credentials,
            new InMemoryTokenStore(),
            ApiConfiguration::forEnvironment(Environment::Sandbox),
        );

        for ($call = 0; $call < 50; ++$call) {
            $gateway->registerMandate(Stacks::tenant(), EmployeeWireSamples::employer());
        }

        static::assertCount(51, $stack->requests());
    }

    public function testTheGivenOptionsAreUsed(): void
    {
        $stack = Stacks::stepping(250_000);
        $stack->respondToken();
        $stack->respond(200, '');
        $configuration = new ApiConfiguration(Environment::Sandbox, null, 'x-correlation-id');
        $gateway = UrssafGatewayFactory::create(
            $stack->transport(),
            $stack->credentials,
            $stack->tokens,
            $configuration,
            $stack->options(),
        );

        $gateway->registerMandate(Stacks::tenant(), EmployeeWireSamples::employer());

        static::assertSame(['tenant-1:030'], $stack->rateLimiter->acquired());
        static::assertSame('request-id-1', $stack->lastRequest()->getHeaderLine('x-correlation-id'));
        static::assertNotSame([], $stack->logger->records());
    }

    public function testTheGivenTokenStoreIsUsed(): void
    {
        $stack = self::stackWithEmptyAnswers();
        $gateway = UrssafGatewayFactory::create(
            $stack->transport(),
            $stack->credentials,
            $stack->tokens,
            $stack->configuration,
        );

        $gateway->registerMandate(Stacks::tenant(), EmployeeWireSamples::employer());

        static::assertCount(1, $stack->tokens->keys());
    }

    public function testALimiterGivenInTheOptionsRefusesBeforeAnyCall(): void
    {
        $stack = Stacks::sandbox();
        $gateway = UrssafGatewayFactory::create(
            $stack->transport(),
            $stack->credentials,
            $stack->tokens,
            $stack->configuration,
            $stack->optionsWith(new RejectingRateLimiter()),
        );

        $this->expectException(RateLimitExceeded::class);

        try {
            $gateway->registerMandate(Stacks::tenant(), EmployeeWireSamples::employer());
        } finally {
            static::assertSame([], $stack->requests());
        }
    }
}
