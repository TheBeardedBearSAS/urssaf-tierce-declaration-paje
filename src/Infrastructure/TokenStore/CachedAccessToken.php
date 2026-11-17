<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\TokenStore;

use DateTimeImmutable;
use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\AccessToken;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;

/**
 * Cache representation of an access token: {value, expiresAt} with the expiry as a UNIX timestamp.
 */
final class CachedAccessToken
{
    /**
     * @return array<string, int|string>
     */
    public static function toArray(#[SensitiveParameter] AccessToken $token): array
    {
        return [
            'value' => $token->value(),
            'expiresAt' => $token->expiresAt()->toDateTimeImmutable()->getTimestamp(),
        ];
    }

    /**
     * Returns null when the cached data is not a valid token representation (treated as a cache miss).
     */
    public static function fromArray(#[SensitiveParameter] mixed $data): ?AccessToken
    {
        if (!\is_array($data) || !\array_key_exists('value', $data) || !\array_key_exists('expiresAt', $data)) {
            return null;
        }

        return self::hydrate($data['value'], $data['expiresAt']);
    }

    private static function hydrate(#[SensitiveParameter] mixed $value, mixed $expiresAt): ?AccessToken
    {
        if (!\is_string($value) || !\is_int($expiresAt)) {
            return null;
        }

        try {
            return AccessToken::of($value, Instant::fromDateTime(new DateTimeImmutable('@' . $expiresAt)));
        } catch (InvalidValue) {
            return null;
        }
    }
}
