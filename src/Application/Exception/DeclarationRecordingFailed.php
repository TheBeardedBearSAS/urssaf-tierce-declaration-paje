<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\DomainException;
use Throwable;

/**
 * Thrown by DeclareSalary when URSSAF accepted 050 / 051 but the accepted result could not be recorded (ADR 0006,
 * settlement table): the record was erased (ForgetEmployer), or the save failed for another reason (storage, outbox) or
 * kept meeting conflicts after the bounded retry. The call is made: never send it again (a second call would be answered
 * by the duplicate code). The original failure, when there is one, is the previous exception.
 *
 * Recovery: run ReconcileStaleDeclarations (the record then leaves "submitting"), then
 * ResolveDeclarationOutcome::asDeclared() with {@see self::documentReference()} (the `referenceDocumentaire` of the
 * answer; null only when URSSAF gave none, then read it on pajemploi.urssaf.fr). With an erased record there is nothing
 * to recover locally.
 *
 * Violation code: "declaration_record.recording_failed". Parameters: the record id, the number of attempts made, the
 * violation code of the last failure (the class name of a failure that carries none) and, when there is one, the
 * `referenceDocumentaire` as "document_reference". None of them is personal data (ADR 0007).
 */
final class DeclarationRecordingFailed extends DomainException
{
    /**
     * @param array<string, scalar> $params
     */
    private function __construct(
        string $message,
        string $code,
        array $params,
        private readonly ?string $documentReference,
        ?Throwable $previous,
    ) {
        parent::__construct($message, $code, $params, $previous);
    }

    public static function after(
        DeclarationRecordId $recordId,
        ?string $documentReference,
        int $attempts,
        string $lastViolationCode,
        ?Throwable $previous,
    ): self {
        $params = ['record_id' => $recordId->value(), 'attempts' => $attempts, 'last_code' => $lastViolationCode];
        if ($documentReference !== null) {
            $params['document_reference'] = $documentReference;
        }

        return new self(
            \sprintf(
                'URSSAF accepted the declaration but the result could not be recorded after %d attempt(s): never send it again, reconcile then resolve it as declared with the document reference (ADR 0006).',
                $attempts,
            ),
            'declaration_record.recording_failed',
            $params,
            $documentReference,
            $previous,
        );
    }

    /**
     * The `referenceDocumentaire` URSSAF answered, null when the answer had none.
     */
    public function documentReference(): ?string
    {
        return $this->documentReference;
    }
}
