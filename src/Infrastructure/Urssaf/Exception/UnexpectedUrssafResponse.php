<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception;

use RuntimeException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\UrssafException;
use Throwable;

/**
 * URSSAF answered with something the contract does not describe (unknown HTTP status, invalid JSON, missing field,
 * unparseable value). Not retryable: the response must be investigated (ADR 0002, ADR 0007).
 *
 * Reasons name the field, never its value: a response may carry personal data.
 */
final class UnexpectedUrssafResponse extends RuntimeException implements UrssafException
{
    public static function invalidPayload(string $reason): self
    {
        return new self(\sprintf('Unexpected URSSAF response: %s.', $reason));
    }

    public static function causedBy(string $reason, Throwable $previous): self
    {
        return new self(\sprintf('Unexpected URSSAF response: %s.', $reason), 0, $previous);
    }

    /**
     * An HTTP status the contract does not map (the code of the exception is the status).
     */
    public static function unexpectedStatus(int $httpStatus): self
    {
        return new self(\sprintf('Unexpected URSSAF response: HTTP %d.', $httpStatus), $httpStatus);
    }
}
