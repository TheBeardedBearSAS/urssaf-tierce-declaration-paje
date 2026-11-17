<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Contract;

use Nyholm\Psr7\Uri;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception\ApiErrorCode;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\UrssafEndpoints;

/**
 * PSR-18 decorator of the contract suite: optionally sends the token request to another URL, records the shape of every
 * exchange (see Exchange) and feeds the passive observations of the report: headers, 429, error codes (spelling of the
 * ER_API_DECLA_* family), error body shapes and response decimals. Exceptions of the inner client are rethrown unchanged.
 *
 * One method per passive observation, hence the method count and the complexity.
 *
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:cyclomatic-complexity
 */
final class SandboxHttpClient implements ClientInterface
{
    /** @var list<Exchange> */
    private array $exchanges = [];

    /** @var list<string> */
    private array $headerNames = [];

    /** @var list<string> */
    private array $codes = [];

    private bool $retryAfterSeen = false;

    private readonly ?string $tokenPath;

    public function __construct(
        private readonly ClientInterface $inner,
        private readonly ?string $tokenUri,
    ) {
        $path = null === $tokenUri ? null : parse_url($tokenUri, \PHP_URL_PATH);
        $this->tokenPath = \is_string($path) ? $path : null;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        if (null !== $this->tokenUri && UrssafEndpoints::TOKEN_PATH === $request->getUri()->getPath()) {
            $request = $request->withUri(new Uri($this->tokenUri));
        }

        return $this->dispatch($request)[0];
    }

    /**
     * Sends the request exactly as given (no token rewrite) and returns what was recorded for it.
     */
    public function probe(RequestInterface $request): Exchange
    {
        return $this->dispatch($request)[1];
    }

    /**
     * @return list<Exchange>
     */
    public function exchanges(): array
    {
        return $this->exchanges;
    }

    /**
     * Whether the exchange is a token request (default token path or the configured token URI's path), not an API call.
     */
    public function isTokenExchange(Exchange $exchange): bool
    {
        return str_ends_with($exchange->pathTemplate, UrssafEndpoints::TOKEN_PATH)
            || null !== $this->tokenPath && str_ends_with($exchange->pathTemplate, $this->tokenPath);
    }

    public function mark(): int
    {
        return \count($this->exchanges);
    }

    /**
     * @return list<Exchange> the exchanges recorded after the mark
     */
    public function since(int $mark): array
    {
        return \array_slice($this->exchanges, $mark);
    }

    public function last(): ?Exchange
    {
        $last = end($this->exchanges);

        return false === $last ? null : $last;
    }

    /**
     * @return array{0: ResponseInterface, 1: Exchange}
     */
    private function dispatch(RequestInterface $request): array
    {
        $response = $this->inner->sendRequest($request);
        $exchange = Exchange::from($request, $response);
        $this->exchanges[] = $exchange;
        $this->observe($exchange);

        return [$response, $exchange];
    }

    private function observe(Exchange $exchange): void
    {
        if ($this->isTokenExchange($exchange)) {
            return;
        }

        $this->observeHeaders($exchange);
        $this->observeCodes($exchange);

        $operation = $exchange->method . ' ' . $exchange->pathTemplate;
        if ($exchange->status >= 400) {
            SandboxReport::merge('error-body-shape', $operation . ' ' . $exchange->status, $exchange->bodyShape, 'http-client');
        }
        if ($exchange->isSuccessful() && (null !== $exchange->amountMaxDecimals || null !== $exchange->rateMaxDecimals)) {
            SandboxReport::merge(
                'response-decimals',
                $operation,
                ['amounts' => $exchange->amountMaxDecimals, 'txPas' => $exchange->rateMaxDecimals],
                'http-client',
            );
        }
        if (429 === $exchange->status) {
            SandboxReport::record(
                'quota-429',
                SandboxReport::OBSERVED,
                [
                    'retryAfter' => $exchange->retryAfter ?? 'absent',
                    'bodyShape' => $exchange->bodyShape,
                    'bodyKeys' => $exchange->bodyKeys,
                    'codes' => $exchange->codes,
                ],
                'http-client',
            );
        }
    }

    private function observeHeaders(Exchange $exchange): void
    {
        $this->retryAfterSeen = $this->retryAfterSeen || null !== $exchange->retryAfter;
        $this->headerNames = array_values(array_unique([...$this->headerNames, ...$exchange->headerNames]));
        sort($this->headerNames);
        SandboxReport::record(
            'headers',
            SandboxReport::OBSERVED,
            ['headerNames' => $this->headerNames, 'retryAfterSeen' => $this->retryAfterSeen],
            'http-client',
        );
    }

    /**
     * Every code seen, raw (normalised) so that the spelling of the ER_API_DECLA_* family (4 or 5 digits) is settled
     * even when the SDK catalogue does not know it yet.
     */
    private function observeCodes(Exchange $exchange): void
    {
        $this->codes = array_values(array_unique([...$this->codes, ...$exchange->allCodes()]));
        sort($this->codes);
        if ([] === $this->codes) {
            return;
        }

        SandboxReport::record(
            'error-codes',
            SandboxReport::OBSERVED,
            [
                'seen' => $this->codes,
                'declaFamily' => array_values(array_filter($this->codes, static fn(string $code): bool => str_starts_with($code, 'ER_API_DECLA_'))),
                'uncatalogued' => array_values(array_filter($this->codes, static fn(string $code): bool => !ApiErrorCode::fromCode($code) instanceof ApiErrorCode)),
            ],
            'http-client',
        );
    }
}
