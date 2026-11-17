<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf;

use RuntimeException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\OperationRetryClass;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RetryableException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\UrssafException;
use Throwable;

/**
 * A never-resent call ({@see OperationRetryClass}) may have been processed by URSSAF, but the
 * answer is lost, a 5xx or unreadable (ADR 0003). It must not be resent automatically: an operator reconciles it.
 * Deliberately not a {@see RetryableException}.
 *
 * The message holds the operation number and the reason only (ADR 0007).
 */
final class OutcomeUnknown extends RuntimeException implements UrssafException
{
    private function __construct(
        string $message,
        private readonly Operation $operation,
        private readonly OutcomeUnknownReason $reason,
        private readonly ?int $httpStatus,
        private readonly ApiErrors $errors,
        Throwable $previous,
    ) {
        parent::__construct($message, $httpStatus ?? 0, $previous);
    }

    /**
     * Converts a transient failure that may have reached URSSAF: a transport error or a 5xx.
     *
     * @throws InvariantViolation "outcome_unknown.not_reached" when the failure cannot have reached URSSAF
     */
    public static function fromUnavailable(UrssafUnavailable $cause): self
    {
        if (!$cause->mayHaveReachedUrssaf()) {
            throw InvariantViolation::because(
                'outcome_unknown.not_reached',
                'Only a failure that may have reached URSSAF has an unknown outcome.',
            );
        }

        $status = $cause->httpStatus();
        $reason = $status === null ? OutcomeUnknownReason::Transport : OutcomeUnknownReason::ServerError;

        return new self(
            self::message($cause->operation(), $reason),
            $cause->operation(),
            $reason,
            $status,
            $cause->errors(),
            $cause,
        );
    }

    /**
     * The write was answered 2xx but the answer could not be read: URSSAF processed it.
     */
    public static function afterUnreadableResponse(Operation $operation, int $httpStatus, UrssafException $cause): self
    {
        return new self(
            self::message($operation, OutcomeUnknownReason::UnreadableResponse),
            $operation,
            OutcomeUnknownReason::UnreadableResponse,
            $httpStatus,
            ApiErrors::none(),
            $cause,
        );
    }

    public function operation(): Operation
    {
        return $this->operation;
    }

    public function reason(): OutcomeUnknownReason
    {
        return $this->reason;
    }

    /**
     * Null when no HTTP answer was received (transport error).
     */
    public function httpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function errors(): ApiErrors
    {
        return $this->errors;
    }

    private static function message(Operation $operation, OutcomeUnknownReason $reason): string
    {
        return \sprintf(
            'The outcome of URSSAF method %s is unknown (%s): the call may have been processed and must not be resent automatically.',
            $operation->value,
            $reason->value,
        );
    }
}
