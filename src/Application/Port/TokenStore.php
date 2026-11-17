<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port;

use SensitiveParameter;

/**
 * Cache of access tokens. Keys are opaque strings built by the authenticator (hash of environment, base URI and client id, ADR 0010);
 * implementations must not log token values.
 */
interface TokenStore
{
    public function get(string $key): ?AccessToken;

    public function save(string $key, #[SensitiveParameter] AccessToken $token): void;

    public function delete(string $key): void;
}
