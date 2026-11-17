<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support;

use Http\Mock\Client;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\RateLimiter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\GatewayOptions;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Http\JsonApiClient;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Http\OAuth2TokenProvider;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\HttpTransport;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\UrssafHttpGateway;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Fake\CapturingLogger;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Fake\InMemoryTokenStore;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Fake\RecordingRateLimiter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Fake\SequenceIdGenerator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Fake\StaticCredentialsProvider;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Fake\SteppingClock;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Fake\TransportFailure;

/**
 * Wiring of the Urssaf infrastructure on top of a PSR-18 mock client and in-memory fakes.
 *
 * One accessor per collaborator of the stack.
 *
 * @mago-expect lint:too-many-methods
 */
final readonly class Stack
{
    public const TOKEN_RESPONSE = '{"access_token":"fictitious-access-token-0001","token_type":"Bearer","expires_in":3600,"scope":"tiersdecl.paje"}';

    public Client $http;
    public InMemoryTokenStore $tokens;
    public RecordingRateLimiter $rateLimiter;
    public SteppingClock $clock;
    public CapturingLogger $logger;
    public StaticCredentialsProvider $credentials;
    public Psr17Factory $factory;
    public SequenceIdGenerator $ids;

    public function __construct(public ApiConfiguration $configuration, int $clockStepMicroseconds)
    {
        $this->http = new Client();
        $this->tokens = new InMemoryTokenStore();
        $this->rateLimiter = new RecordingRateLimiter();
        $this->clock = new SteppingClock($clockStepMicroseconds);
        $this->logger = new CapturingLogger();
        $this->credentials = new StaticCredentialsProvider();
        $this->factory = new Psr17Factory();
        $this->ids = new SequenceIdGenerator();
    }

    /**
     * Fetches and caches a token now, so that a later failure queued on the mock client hits the API call itself.
     */
    public function warmUpToken(): void
    {
        $this->respondToken();
        $this->tokenProvider()->accessToken(
            $this->credentials->forTenant(Stacks::tenant()),
            Operation::VerifyEmployer,
        );
        $this->logger->clear();
    }

    public function options(): GatewayOptions
    {
        return $this->optionsWith($this->rateLimiter);
    }

    public function optionsWith(RateLimiter $rateLimiter): GatewayOptions
    {
        return GatewayOptions::defaults()
            ->withRateLimiter($rateLimiter)
            ->withIdGenerator($this->ids)
            ->withClock($this->clock)
            ->withLogger($this->logger);
    }

    public function transport(): HttpTransport
    {
        return new HttpTransport($this->http, $this->factory, $this->factory);
    }

    public function tokenProvider(): OAuth2TokenProvider
    {
        return new OAuth2TokenProvider($this->transport(), $this->tokens, $this->configuration, $this->options());
    }

    public function jsonClient(): JsonApiClient
    {
        return $this->jsonClientWith($this->rateLimiter);
    }

    public function gateway(): UrssafHttpGateway
    {
        return new UrssafHttpGateway($this->jsonClient(), $this->configuration);
    }

    public function jsonClientWith(RateLimiter $rateLimiter): JsonApiClient
    {
        $options = $this->optionsWith($rateLimiter);

        return new JsonApiClient(
            $this->transport(),
            new OAuth2TokenProvider($this->transport(), $this->tokens, $this->configuration, $options),
            $this->credentials,
            $this->configuration,
            $options,
        );
    }

    /**
     * @return list<RequestInterface>
     */
    public function requests(): array
    {
        return array_values($this->http->getRequests());
    }

    public function lastRequest(): RequestInterface
    {
        $requests = $this->requests();
        $last = array_pop($requests);

        return $last ?? throw new LogicException('No request was sent.');
    }

    /**
     * @param array<string, string> $headers
     */
    public function respond(int $status, string $body, array $headers = []): void
    {
        $this->http->addResponse(new Response($status, $headers, $body));
    }

    public function respondToken(): void
    {
        $this->respond(200, self::TOKEN_RESPONSE);
    }

    public function fail(TransportFailure $exception): void
    {
        $this->http->addException($exception);
    }
}
