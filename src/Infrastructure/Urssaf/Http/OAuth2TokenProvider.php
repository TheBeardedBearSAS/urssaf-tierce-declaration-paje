<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Http;

use DateInterval;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\AccessToken;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Credentials;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\TokenStore;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\GatewayOptions;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\HttpTransport;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\UrssafEndpoints;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonDecoder;

/**
 * OAuth2 client-credentials flow with a shared token cache (ADR 0002, ADR 0010, api-reference section 1).
 *
 * The cache key is `urssaf_paje.<sha256(environment|baseUri|clientId)>` (PSR-6 safe, never contains the secret; the
 * prefix differs from the sibling packages so that a shared pool never mixes scopes). Nothing secret (client secret,
 * token) is ever logged. A token failure never has an unknown outcome: the API call itself was not sent.
 *
 * @internal
 */
final readonly class OAuth2TokenProvider
{
    private const KEY_PREFIX = 'urssaf_paje.';

    public function __construct(
        private HttpTransport $transport,
        private TokenStore $tokenStore,
        private ApiConfiguration $configuration,
        private GatewayOptions $options,
    ) {}

    /**
     * Returns a valid bearer token, fetching a new one when the cached one is missing or about to expire.
     *
     * @throws UrssafAuthenticationFailed token endpoint answered 400, 401 or 403
     * @throws UrssafUnavailable          token endpoint answered 429 / 5xx (512 included) or could not be reached (the
     *                                    API call itself is not sent: {@see UrssafUnavailable::mayHaveReachedUrssaf()}
     *                                    is false)
     * @throws UnexpectedUrssafResponse   any other status, unreadable token response
     */
    public function accessToken(Credentials $credentials, Operation $operation): string
    {
        $key = $this->cacheKey($credentials);
        $cached = $this->tokenStore->get($key);

        if ($cached instanceof AccessToken && !$cached->isExpired(
            $this->options->clock()->now(),
            $this->configuration->tokenSafetyMarginSeconds(),
        )) {
            return $cached->value();
        }

        $token = $this->fetch($credentials, $operation);
        $this->tokenStore->save($key, $token);

        return $token->value();
    }

    public function invalidate(Credentials $credentials): void
    {
        $this->tokenStore->delete($this->cacheKey($credentials));
    }

    /**
     * Public for the tests of the cache contract (ADR 0010).
     */
    public function cacheKey(Credentials $credentials): string
    {
        return self::KEY_PREFIX . hash('sha256', \sprintf(
            '%s|%s|%s',
            $this->configuration->environment()->value,
            $this->configuration->baseUri(),
            $credentials->clientId(),
        ));
    }

    private function fetch(Credentials $credentials, Operation $operation): AccessToken
    {
        $body = http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => $credentials->clientId(),
            'client_secret' => $credentials->clientSecret(),
            'scope' => UrssafEndpoints::TOKEN_SCOPE,
        ], '', '&', PHP_QUERY_RFC3986);

        $request = $this->transport
            ->requestFactory()
            ->createRequest('POST', $this->configuration->tokenUri())
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', $this->configuration->userAgent())
            ->withBody($this->transport->streamFactory()->createStream($body));

        $logger = $this->options->logger();
        $clock = $this->options->clock();
        $startedAt = $clock->now();

        try {
            $response = $this->transport->client()->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            $logger->warning('URSSAF token request failed: transport error.', ['exception' => $exception::class]);

            throw UrssafUnavailable::duringAuthentication(
                $operation,
                null,
                null,
                TransportError::fromClientException($exception),
            );
        }

        $logger->info('URSSAF token request.', [
            'method' => 'POST',
            'path' => UrssafEndpoints::TOKEN_PATH,
            'status' => $response->getStatusCode(),
            'duration_ms' => Duration::millisecondsSince($startedAt, $clock->now()),
        ]);

        return $this->tokenFrom($response, $operation);
    }

    private function tokenFrom(ResponseInterface $response, Operation $operation): AccessToken
    {
        $status = $response->getStatusCode();

        if (\in_array($status, [400, 401, 403], true)) {
            throw UrssafAuthenticationFailed::withStatus($status);
        }

        if (429 === $status || $status >= 500) {
            throw UrssafUnavailable::duringAuthentication($operation, $status, RetryAfter::seconds($response), null);
        }

        if ($status < 200 || $status >= 300) {
            throw UnexpectedUrssafResponse::unexpectedStatus($status);
        }

        $payload = JsonDecoder::decode((string) $response->getBody());
        // No requireJsonObject(): the required fields below already reject an empty body or a list.
        $lifetime = $payload->number('expires_in');
        if (null === $lifetime || (int) $lifetime <= 0) {
            throw UnexpectedUrssafResponse::invalidPayload('field "expires_in" is missing or not positive');
        }

        return AccessToken::of(
            $payload->requireString('access_token'),
            Instant::fromDateTime($this->options->clock()->now()->add(new DateInterval('PT' . (int) $lifetime . 'S'))),
        );
    }
}
