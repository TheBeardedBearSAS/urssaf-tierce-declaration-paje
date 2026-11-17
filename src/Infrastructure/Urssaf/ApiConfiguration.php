<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf;

use DateTimeZone;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\OperationRetryClass;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;

/**
 * Immutable connection settings of the URSSAF gateway (ADR 0002, ADR 0003, ADR 0010).
 *
 * Positional arguments only; {@see self::forEnvironment()} gives the defaults of an environment.
 */
final readonly class ApiConfiguration
{
    public const DEFAULT_TOKEN_SAFETY_MARGIN_SECONDS = 60;
    public const DEFAULT_USER_AGENT = 'thebeardedbear-urssaf-tierce-declaration-paje';
    public const DEFAULT_BUSINESS_TIME_ZONE = 'Europe/Paris';

    public const BASE_URI_INVALID = 'api_configuration.base_uri_invalid';
    public const USER_AGENT_INVALID = 'api_configuration.user_agent_invalid';
    public const DUPLICATE_HEADER = 'api_configuration.duplicate_header';
    public const RETRY_SAFE_WRITE_INVALID = 'api_configuration.retry_safe_write_invalid';

    private const BASE_URI_PATTERN = '/^https?:\/\/[^\s\/?#]+(?:\/[^\s?#]*)?$/Di';
    private const USER_AGENT_PATTERN = '/^[\x21-\x7E](?:[\x20-\x7E]*[\x21-\x7E])?$/D';

    /**
     * @param string|null     $baseUriOverride          replaces the environment host (e.g. the sandbox host or a proxy)
     * @param string|null     $requestIdHeaderName      name of the optional request-id header; its value is a UUID
     *                                                  generated for each attempt (omitted when null)
     * @param HttpHeader|null $applicationIdHeader      optional header with a fixed value (omitted when null)
     * @param int             $tokenSafetyMarginSeconds a cached token is considered expired this many seconds early
     * @param DateTimeZone    $businessTimeZone         time zone of "today" for the Application; the gateway itself
     *                                                  does not use it
     * @param list<Operation> $retrySafeWrites          023, 030 and 031 the host has found idempotent on the sandbox
     *                                                  (ADR 0003): they are then retried like a read
     *
     * @throws InvalidValue "api_configuration.base_uri_invalid", "token_safety_margin.negative",
     *                      "api_configuration.user_agent_invalid", "http_header.*", "api_configuration.duplicate_header",
     *                      "api_configuration.retry_safe_write_invalid"
     */
    public function __construct(
        private Environment $environment,
        private ?string $baseUriOverride = null,
        private ?string $requestIdHeaderName = null,
        private ?HttpHeader $applicationIdHeader = null,
        private int $tokenSafetyMarginSeconds = self::DEFAULT_TOKEN_SAFETY_MARGIN_SECONDS,
        private string $userAgent = self::DEFAULT_USER_AGENT,
        private DateTimeZone $businessTimeZone = new DateTimeZone(self::DEFAULT_BUSINESS_TIME_ZONE),
        private array $retrySafeWrites = [],
    ) {
        if (null !== $baseUriOverride && 1 !== preg_match(self::BASE_URI_PATTERN, $baseUriOverride)) {
            throw InvalidValue::because(
                self::BASE_URI_INVALID,
                'The base URI must be an absolute http(s) URI without query or fragment.',
            );
        }

        if ($tokenSafetyMarginSeconds < 0) {
            throw InvalidValue::negative('token_safety_margin');
        }

        if (1 !== preg_match(self::USER_AGENT_PATTERN, $userAgent)) {
            throw InvalidValue::because(
                self::USER_AGENT_INVALID,
                'The user agent must be visible ASCII without line breaks.',
            );
        }

        if (null !== $requestIdHeaderName) {
            HttpHeader::validName($requestIdHeaderName);

            if (
                $applicationIdHeader instanceof HttpHeader
                && strtolower($requestIdHeaderName) === strtolower($applicationIdHeader->name())
            ) {
                throw InvalidValue::because(
                    self::DUPLICATE_HEADER,
                    'The request-id and application-id headers must have different names.',
                );
            }
        }

        foreach ($retrySafeWrites as $operation) {
            if (OperationRetryClass::UnspecifiedWrite !== $operation->retryClass()) {
                throw InvalidValue::because(
                    self::RETRY_SAFE_WRITE_INVALID,
                    'Only the writes of unspecified idempotency (023, 030, 031) can be declared safe to retry.',
                );
            }
        }
    }

    public static function forEnvironment(Environment $environment): self
    {
        return new self($environment);
    }

    public function environment(): Environment
    {
        return $this->environment;
    }

    public function baseUri(): string
    {
        return rtrim($this->baseUriOverride ?? $this->environment->baseUri(), '/');
    }

    public function tokenUri(): string
    {
        return $this->baseUri() . UrssafEndpoints::TOKEN_PATH;
    }

    /**
     * @param string $path a path of the wire layer (`ApiPaths`), starting with a slash
     */
    public function apiUri(string $path): string
    {
        return $this->baseUri() . UrssafEndpoints::API_BASE_PATH . $path;
    }

    public function tokenSafetyMarginSeconds(): int
    {
        return $this->tokenSafetyMarginSeconds;
    }

    public function userAgent(): string
    {
        return $this->userAgent;
    }

    public function businessTimeZone(): DateTimeZone
    {
        return $this->businessTimeZone;
    }

    public function requestIdHeaderName(): ?string
    {
        return $this->requestIdHeaderName;
    }

    public function applicationIdHeader(): ?HttpHeader
    {
        return $this->applicationIdHeader;
    }

    /**
     * @return list<Operation>
     */
    public function retrySafeWrites(): array
    {
        return $this->retrySafeWrites;
    }

    /**
     * Whether a transport error or a 5xx on this operation is a plain retryable failure (a read, a pre-declaration or
     * a write declared safe) rather than an unknown outcome (ADR 0003).
     */
    public function isSafeToRetry(Operation $operation): bool
    {
        return OperationRetryClass::Safe === $operation->retryClass()
            || \in_array($operation, $this->retrySafeWrites, true);
    }
}
