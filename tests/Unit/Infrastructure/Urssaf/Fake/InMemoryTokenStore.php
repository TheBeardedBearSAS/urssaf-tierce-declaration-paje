<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Fake;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\AccessToken;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\TokenStore;

final class InMemoryTokenStore implements TokenStore
{
    /** @var array<string, AccessToken> */
    private array $tokens = [];

    public function get(string $key): ?AccessToken
    {
        return $this->tokens[$key] ?? null;
    }

    public function save(string $key, #[SensitiveParameter] AccessToken $token): void
    {
        $this->tokens[$key] = $token;
    }

    public function delete(string $key): void
    {
        unset($this->tokens[$key]);
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->tokens);
    }
}
