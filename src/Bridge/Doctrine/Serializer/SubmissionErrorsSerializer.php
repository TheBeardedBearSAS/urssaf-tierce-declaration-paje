<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Serializer;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\SubmissionError;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;

/**
 * Explicit JSON shape of the errors of the last failed or rejected submission: the code and the kind, and the message
 * and the description URSSAF sent (optional, personal data: ADR 0007).
 *
 * @phpstan-type ErrorData array{code: string, kind: string, message: string|null, description: string|null}
 */
final class SubmissionErrorsSerializer
{
    /**
     * @param list<SubmissionError> $errors
     *
     * @return list<ErrorData>
     */
    public static function toArray(array $errors): array
    {
        return array_map(
            static fn(SubmissionError $error): array => [
                'code' => $error->code(),
                'kind' => $error->kind(),
                'message' => $error->message(),
                'description' => $error->description(),
            ],
            $errors,
        );
    }

    /**
     * @param array<array-key, mixed> $data the document, of shape list<ErrorData>
     *
     * @return list<SubmissionError>
     *
     * @throws InvalidValue
     */
    public static function fromArray(array $data): array
    {
        /** @var list<ErrorData> $data */
        return array_map(
            static fn(array $error): SubmissionError => SubmissionError::of($error['code'], $error['kind'], $error['message'], $error['description']),
            $data,
        );
    }
}
