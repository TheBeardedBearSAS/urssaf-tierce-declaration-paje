<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Http;

use JsonException;
use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonEncoder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonObject;

/**
 * Description of one API call, independent from the HTTP library (ADR 0002): GET, DELETE (030 / 031 style, query
 * only) or POST with a query and / or a JSON body.
 *
 * Queries carry employer numbers, NIR and names, bodies carry birth dates and salaries (ADR 0007): the factories mark
 * them sensitive and {@see self::__debugInfo()} never shows their values. The path holds the employer number as well,
 * so it is never logged either (the client logs a template).
 *
 * @internal
 */
final readonly class ApiRequest
{
    /**
     * @param array<string, string> $query
     */
    private function __construct(
        private Operation $operation,
        private string $method,
        private string $path,
        #[SensitiveParameter]
        private array $query,
        #[SensitiveParameter]
        private ?string $jsonBody,
    ) {}

    /**
     * @param array<string, string> $query
     */
    public static function get(Operation $operation, string $path, #[SensitiveParameter] array $query = []): self
    {
        return new self($operation, 'GET', $path, $query, null);
    }

    /**
     * @param array<string, string>                   $query
     * @param array<array-key, mixed>|JsonObject|null $body  null means no body (030); use a {@see JsonObject} for the
     *                                                       `{}` of 021 and 023 (ADR 0002)
     *
     * @throws InvariantViolation "json_encoder.unsupported_value"
     * @throws JsonException      for invalid UTF-8 strings
     */
    public static function post(
        Operation $operation,
        string $path,
        #[SensitiveParameter]
        array $query = [],
        #[SensitiveParameter]
        array|JsonObject|null $body = null,
    ): self {
        return new self($operation, 'POST', $path, $query, null === $body ? null : JsonEncoder::encode($body));
    }

    /**
     * @param array<string, string> $query
     */
    public static function delete(Operation $operation, string $path, #[SensitiveParameter] array $query = []): self
    {
        return new self($operation, 'DELETE', $path, $query, null);
    }

    public function operation(): Operation
    {
        return $this->operation;
    }

    public function method(): string
    {
        return $this->method;
    }

    /**
     * Relative to {@see \TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\UrssafEndpoints::API_BASE_PATH}.
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return array<string, string>
     */
    public function query(): array
    {
        return $this->query;
    }

    public function jsonBody(): ?string
    {
        return $this->jsonBody;
    }

    /**
     * @return array{operation: string, method: string, path: string, query: list<int|string>, jsonBody: string|null}
     */
    public function __debugInfo(): array
    {
        return [
            'operation' => $this->operation->value,
            'method' => $this->method,
            'path' => '***',
            'query' => array_keys($this->query),
            'jsonBody' => null === $this->jsonBody ? null : '***',
        ];
    }
}
