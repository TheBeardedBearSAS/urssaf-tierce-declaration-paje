<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception;

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\DomainException;
use Throwable;

/**
 * Thrown by RegisterMandate (030) and CancelMandate (031) when URSSAF accepted the call but the local state could not be
 * recorded (ADR 0006): every attempt met a conflict (other writers kept changing the account or the mandate), or an
 * attempt failed for another reason (a violation that is not a conflict, a storage or outbox failure). The call is made:
 * never call it again. The recovery is call-free: ImportMandate after a failed 030, RecordMandateCancellation after a
 * failed 031. The original failure is the previous exception.
 *
 * Violation code: "mandate.recording_failed". Parameters: the method ("030" or "031"), the number of attempts made and
 * the violation code of the last attempt (the class name of a failure that carries none).
 */
final class MandateRecordingFailed extends DomainException
{
    public static function after(Operation $operation, int $attempts, string $lastViolationCode, ?Throwable $previous): self
    {
        return new self(
            \sprintf(
                'URSSAF accepted method %s but the local state could not be recorded after %d attempt(s): record it without any call (ADR 0006).',
                $operation->value,
                $attempts,
            ),
            'mandate.recording_failed',
            ['operation' => $operation->value, 'attempts' => $attempts, 'last_code' => $lastViolationCode],
            $previous,
        );
    }
}
