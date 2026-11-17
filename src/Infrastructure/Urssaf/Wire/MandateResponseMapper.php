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
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * 030 and 031 answer an empty 200 (api-reference §2.11, §2.12, ADR 0002): a blank body and `{}` are accepted, anything
 * else (`[]` included) is not a documented answer. The body is never put in the exception (ADR 0007).
 */
final class MandateResponseMapper
{
    /**
     * @param string $body raw body of the 2xx answer
     *
     * @throws UnexpectedUrssafResponse when the body is not blank or `{}` (or is not JSON)
     */
    public static function assertEmpty(#[SensitiveParameter] string $body): void
    {
        $payload = JsonDecoder::decode($body);

        if (trim($body) === '') {
            return;
        }

        $payload->requireJsonObject();

        if (!$payload->isEmpty()) {
            throw UnexpectedUrssafResponse::invalidPayload('expected an empty body');
        }
    }
}
