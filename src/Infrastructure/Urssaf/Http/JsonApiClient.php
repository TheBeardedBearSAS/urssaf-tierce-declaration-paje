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

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Credentials;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\GatewayOptions;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\HttpHeader;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\HttpTransport;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\ErrorClassifier;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\ErrorPayloadParser;

/**
 * Authenticated JSON transport over PSR-18 (ADR 0002, ADR 0003, ADR 0007, ADR 0010, ADR 0011).
 *
 * Per attempt: the tenant's rate limiter, bearer token, optional request-id header (a new id per attempt) and
 * optional application-id header. A 401 invalidates the cached token and the call is replayed exactly once, for every
 * operation (a 401 proves the call was not processed). Statuses are then mapped to the gateway exceptions; on an
 * operation that is not safe to retry (050, 051, and 023, 030, 031 unless declared safe in
 * {@see ApiConfiguration::retrySafeWrites()}) a transport error or any 5xx other than 512 becomes an
 * {@see OutcomeUnknown}. A 429 stays a retryable {@see UrssafUnavailable} for every operation.
 *
 * A 2xx answer is returned raw: the gateway decodes it, and turns an unreadable one of a never-resent operation into
 * {@see OutcomeUnknown::afterUnreadableResponse()} (it catches the {@see UnexpectedUrssafResponse} of the mappers).
 *
 * Neither the token, the URI, the query nor request / response bodies are logged or put in an exception message
 * (ADR 0007); the path is logged as a template (the employer number is replaced).
 *
 * The status table of ADR 0002 / 0003 is one decision per branch, hence the complexity.
 *
 * @internal
 * @mago-expect lint:cyclomatic-complexity
 */
final readonly class JsonApiClient
{
    private const EMPLOYER_PATH_PATTERN = '#^/employeurs/[^/]+#';
    private const EMPLOYER_PATH_TEMPLATE = '/employeurs/{numeroPajeEmployeur}';

    public function __construct(
        private HttpTransport $transport,
        private OAuth2TokenProvider $tokenProvider,
        private CredentialsProvider $credentialsProvider,
        private ApiConfiguration $configuration,
        private GatewayOptions $options,
        private ErrorPayloadParser $errorParser = new ErrorPayloadParser(),
    ) {}

    /**
     * Sends one call (plus one replay after a 401) and returns the raw 2xx answer; the caller decodes it.
     *
     * @throws RecordNotFound             no credentials for the tenant
     * @throws RateLimitExceeded          local limiter (before any HTTP call)
     * @throws UrssafRequestRejected      HTTP 400, classified by {@see ErrorClassifier::classify()}
     * @throws UrssafAuthenticationFailed 401 after the replay, 403, 512; token 400 / 401 / 403
     * @throws UrssafUnavailable          429; 500 / 502 / 503 / 504 and transport errors of safe operations; token
     *                                    failures
     * @throws OutcomeUnknown             unsafe operations only: transport error, any 5xx other than 512
     * @throws UnexpectedUrssafResponse   any other status; token response unreadable
     */
    public function send(TenantId $tenantId, ApiRequest $request): ApiResponse
    {
        $credentials = $this->credentialsProvider->forTenant($tenantId);
        $response = $this->attempt($tenantId, $credentials, $request);

        if (401 === $response->getStatusCode()) {
            $this->tokenProvider->invalidate($credentials);
            $this->options->logger()->info('URSSAF call answered 401: token invalidated, replaying once.', [
                'operation' => $request->operation()->value,
                'path' => $this->pathTemplate($request),
            ]);
            $response = $this->attempt($tenantId, $credentials, $request);
        }

        return $this->interpret($request->operation(), $response);
    }

    private function attempt(
        TenantId $tenantId,
        Credentials $credentials,
        ApiRequest $request,
    ): ResponseInterface {
        $operation = $request->operation();
        $this->options->rateLimiter()->acquire($tenantId, $operation);

        $token = $this->tokenProvider->accessToken($credentials, $operation);
        $requestIdHeaderName = $this->configuration->requestIdHeaderName();
        $requestIdHeader = null === $requestIdHeaderName
            ? null
            : [$requestIdHeaderName, $this->options->idGenerator()->generate()];
        $requestId = $requestIdHeader[1] ?? null;
        $httpRequest = $this->buildRequest($request, $token, $requestIdHeader);
        $context = [
            'operation' => $operation->value,
            'method' => $request->method(),
            'path' => $this->pathTemplate($request),
        ];
        $clock = $this->options->clock();
        $startedAt = $clock->now();

        try {
            $response = $this->transport->client()->sendRequest($httpRequest);
        } catch (ClientExceptionInterface $exception) {
            $this->options->logger()->warning('URSSAF call failed: transport error.', $this->withRequestId($context + [
                'duration_ms' => Duration::millisecondsSince($startedAt, $clock->now()),
                'exception' => $exception::class,
            ], $requestId));

            throw $this->failure(UrssafUnavailable::dueToTransportError(
                $operation,
                TransportError::fromClientException($exception),
            ));
        }

        $this->options->logger()->info('URSSAF call.', $this->withRequestId($context + [
            'status' => $response->getStatusCode(),
            'duration_ms' => Duration::millisecondsSince($startedAt, $clock->now()),
        ], $requestId));

        return $response;
    }

    /**
     * A transient failure that may have reached URSSAF: retryable on a safe operation, an unknown outcome otherwise.
     */
    private function failure(UrssafUnavailable $unavailable): UrssafUnavailable|OutcomeUnknown
    {
        return $this->configuration->isSafeToRetry($unavailable->operation())
            ? $unavailable
            : OutcomeUnknown::fromUnavailable($unavailable);
    }

    /**
     * @param array<string, int|string> $context
     *
     * @return array<string, int|string>
     */
    private function withRequestId(array $context, ?string $requestId): array
    {
        return null === $requestId ? $context : $context + ['request_id' => $requestId];
    }

    /**
     * The path with the employer number replaced: it is personal data (ADR 0007).
     */
    private function pathTemplate(ApiRequest $request): string
    {
        return (string) preg_replace(self::EMPLOYER_PATH_PATTERN, self::EMPLOYER_PATH_TEMPLATE, $request->path());
    }

    /**
     * @param array{string, string}|null $requestIdHeader the configured request-id header name and its generated value
     */
    private function buildRequest(
        ApiRequest $request,
        #[SensitiveParameter]
        string $token,
        ?array $requestIdHeader,
    ): RequestInterface {
        $uri = $this->configuration->apiUri($request->path());
        if ([] !== $request->query()) {
            $uri .= '?' . http_build_query($request->query(), '', '&', PHP_QUERY_RFC3986);
        }

        $httpRequest = $this->transport
            ->requestFactory()
            ->createRequest($request->method(), $uri)
            ->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', $this->configuration->userAgent());

        if (null !== $requestIdHeader) {
            $httpRequest = $httpRequest->withHeader($requestIdHeader[0], $requestIdHeader[1]);
        }

        $applicationId = $this->configuration->applicationIdHeader();
        if ($applicationId instanceof HttpHeader) {
            $httpRequest = $httpRequest->withHeader($applicationId->name(), $applicationId->value());
        }

        $body = $request->jsonBody();
        if (null !== $body) {
            $httpRequest = $httpRequest
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->transport->streamFactory()->createStream($body));
        }

        return $httpRequest;
    }

    private function interpret(Operation $operation, ResponseInterface $response): ApiResponse
    {
        $status = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($status >= 200 && $status < 300) {
            return new ApiResponse($status, $body);
        }

        if (400 === $status) {
            $errors = $this->errorParser->parseBody($body);

            throw UrssafRequestRejected::withErrors($operation, $status, $errors, ErrorClassifier::classify($errors));
        }

        if (\in_array($status, [401, 403, 512], true)) {
            throw UrssafAuthenticationFailed::withStatus($status);
        }

        if (429 === $status || $status >= 500) {
            throw $this->unavailable($operation, $response, $body);
        }

        throw UnexpectedUrssafResponse::unexpectedStatus($status);
    }

    /**
     * 429 (retryable on every operation) or a 5xx other than 512.
     */
    private function unavailable(
        Operation $operation,
        ResponseInterface $response,
        #[SensitiveParameter]
        string $body,
    ): UrssafUnavailable|OutcomeUnknown|UnexpectedUrssafResponse {
        $status = $response->getStatusCode();

        if (429 !== $status && !$this->configuration->isSafeToRetry($operation)) {
            return OutcomeUnknown::fromUnavailable($this->withStatus($operation, $response, $body));
        }

        if (429 === $status || \in_array($status, [500, 502, 503, 504], true)) {
            return $this->withStatus($operation, $response, $body);
        }

        return UnexpectedUrssafResponse::unexpectedStatus($status);
    }

    private function withStatus(
        Operation $operation,
        ResponseInterface $response,
        #[SensitiveParameter]
        string $body,
    ): UrssafUnavailable {
        return UrssafUnavailable::withStatus(
            $operation,
            $response->getStatusCode(),
            RetryAfter::seconds($response),
            $this->errorParser->parseBody($body),
        );
    }
}
