<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception\ApiErrorCode;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * Parses the error payload shapes of the API (api-reference §1.3, ADR 0005): a list of `{code,message,description}`
 * objects (Swagger), a single object (mandate operations and the PDF examples) or an envelope `{"errors": [...]}`
 * (tolerated). Codes are normalised (whitespace removed); messages and descriptions are kept raw, a missing one
 * stays null. Entries without a code are dropped.
 */
final class ErrorPayloadParser
{
    private const ENVELOPE = 'errors';

    /**
     * Lenient: an unreadable body yields no error, and the entries of a list that are not objects are skipped, so that
     * one malformed entry never hides the others, e.g. a duplicate-declaration code (used for HTTP error responses,
     * which may be anything).
     */
    public function parseBody(#[SensitiveParameter] string $body): ApiErrors
    {
        try {
            return $this->parseLenient(JsonDecoder::decode($body));
        } catch (UnexpectedUrssafResponse) {
            return ApiErrors::none();
        }
    }

    /**
     * @throws UnexpectedUrssafResponse when the structure is neither an object, a list of objects nor an envelope
     */
    public function parsePayload(Payload $payload): ApiErrors
    {
        if ($payload->isList()) {
            return $this->parseEntries($payload->entries());
        }

        if ($payload->string('code') !== null) {
            return $this->parseEntries([$payload]);
        }

        return $this->parseEntries($payload->items(self::ENVELOPE));
    }

    private function parseLenient(Payload $payload): ApiErrors
    {
        if ($payload->isList()) {
            return $this->parseEntries($payload->lenientEntries());
        }

        if ($payload->string('code') !== null) {
            return $this->parseEntries([$payload]);
        }

        return $this->parseEntries($payload->lenientItems(self::ENVELOPE));
    }

    /**
     * @param list<Payload> $entries
     */
    private function parseEntries(array $entries): ApiErrors
    {
        $errors = [];
        foreach ($entries as $entry) {
            $code = ApiErrorCode::normalise($entry->string('code') ?? '');
            if ($code === '') {
                continue;
            }

            $errors[] = ApiError::of($code, $entry->string('message'), $entry->string('description'));
        }

        return ApiErrors::of(...$errors);
    }
}
