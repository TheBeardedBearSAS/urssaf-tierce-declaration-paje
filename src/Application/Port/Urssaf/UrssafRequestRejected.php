<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf;

use RuntimeException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\UrssafException;

/**
 * URSSAF refused the whole call (HTTP 400, ADR 0002). Not retryable as is. The gateway classifies the errors
 * ({@see RejectionKind}, ADR 0005): use {@see self::kind()} rather than comparing error codes or texts. With several
 * errors, the kind is the one decided by the classifier for the whole answer.
 *
 * The message holds the operation number, the HTTP status and the error codes only: never a message or a description,
 * which may echo a value of the request (ADR 0007).
 */
final class UrssafRequestRejected extends RuntimeException implements UrssafException
{
    private function __construct(
        string $message,
        private readonly Operation $operation,
        private readonly int $httpStatus,
        private readonly ApiErrors $errors,
        private readonly RejectionKind $kind,
    ) {
        parent::__construct($message, $httpStatus);
    }

    public static function withErrors(Operation $operation, int $httpStatus, ApiErrors $errors, RejectionKind $kind): self
    {
        return new self(
            \sprintf(
                'URSSAF method %s rejected the request (HTTP %d, %s): %s.',
                $operation->value,
                $httpStatus,
                $kind->value,
                $errors->isEmpty() ? 'no error code' : implode(', ', $errors->codes()),
            ),
            $operation,
            $httpStatus,
            $errors,
            $kind,
        );
    }

    public function operation(): Operation
    {
        return $this->operation;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    public function errors(): ApiErrors
    {
        return $this->errors;
    }

    public function hasErrorCode(string $code): bool
    {
        return $this->errors->hasCode($code);
    }

    public function kind(): RejectionKind
    {
        return $this->kind;
    }

    public function is(RejectionKind $kind): bool
    {
        return $this->kind === $kind;
    }
}
