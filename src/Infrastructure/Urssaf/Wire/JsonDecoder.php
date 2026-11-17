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

use JsonException;
use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * Decodes an HTTP body into a {@see Payload}. An empty (or blank) body gives an empty payload, which is not a JSON
 * object: 030 and 031 answer an empty 200 (ADR 0002), and every other response mapper starts with
 * {@see Payload::requireJsonObject()}, so that an empty body or a top-level list never reads as a success whose fields
 * are all absent. `{}` gives an empty object payload (`json_decode()` cannot tell it from `[]`, so the first character
 * decides).
 *
 * Integers beyond the platform range are kept as digit strings (`JSON_BIGINT_AS_STRING`), never turned into floats.
 */
final class JsonDecoder
{
    /**
     * @throws UnexpectedUrssafResponse when the body is not valid JSON or not a JSON object / array
     */
    public static function decode(#[SensitiveParameter] string $json): Payload
    {
        if (trim($json) === '') {
            return Payload::empty();
        }

        $payload = self::tryDecode($json) ?? throw UnexpectedUrssafResponse::invalidPayload('body is not valid JSON');

        return $payload->isEmpty() && str_starts_with(ltrim($json), '{') ? Payload::emptyObject() : $payload;
    }

    /**
     * Null when the body is not valid JSON. The JsonException is dropped, never chained: its stack trace arguments
     * would hold the raw body (ADR 0007).
     *
     * @throws UnexpectedUrssafResponse when the body is neither a JSON object nor a JSON array
     */
    private static function tryDecode(#[SensitiveParameter] string $json): ?Payload
    {
        try {
            return Payload::fromDecoded(json_decode($json, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING));
        } catch (JsonException) {
            return null;
        }
    }
}
