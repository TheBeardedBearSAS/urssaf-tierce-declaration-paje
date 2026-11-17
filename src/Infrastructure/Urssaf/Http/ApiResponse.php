<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Http;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\Payload;

/**
 * Successful (2xx) answer of an API call. The body is kept raw and decoded lazily, so that the gateway (not the
 * client) decides what an unreadable 2xx means: on a never-resent operation it is an unknown outcome (ADR 0003), the
 * gateway catches the {@see UnexpectedUrssafResponse} of {@see self::payload()} and of the response mappers.
 *
 * @internal
 */
final readonly class ApiResponse
{
    public function __construct(
        private int $status,
        #[SensitiveParameter]
        private string $body,
    ) {}

    /**
     * The 2xx status actually received.
     */
    public function status(): int
    {
        return $this->status;
    }

    /**
     * No payload at all (ADR 0002): an empty or blank body (030 and 031 answer an empty 200).
     */
    public function isEmpty(): bool
    {
        return '' === trim($this->body);
    }

    /**
     * The raw body (personal data: never log it).
     */
    public function body(): string
    {
        return $this->body;
    }

    /**
     * @throws UnexpectedUrssafResponse when the body is not valid JSON
     */
    public function payload(): Payload
    {
        return JsonDecoder::decode($this->body);
    }

    /**
     * @return array{status: int, body: string}
     */
    public function __debugInfo(): array
    {
        return [
            'status' => $this->status,
            'body' => '' === $this->body ? '' : '***',
        ];
    }
}
