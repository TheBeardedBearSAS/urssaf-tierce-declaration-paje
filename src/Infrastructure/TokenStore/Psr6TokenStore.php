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

use Psr\Cache\CacheItemPoolInterface;
use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\AccessToken;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\TokenStore;

/**
 * Token cache on a PSR-6 pool. The cache item expires with the token itself.
 */
final readonly class Psr6TokenStore implements TokenStore
{
    public function __construct(private CacheItemPoolInterface $pool) {}

    public function get(string $key): ?AccessToken
    {
        $cacheKey = CacheKey::of($key);
        $item = $this->pool->getItem($cacheKey);
        if (!$item->isHit()) {
            return null;
        }

        $token = CachedAccessToken::fromArray($item->get());
        if (!$token instanceof AccessToken) {
            $this->pool->deleteItem($cacheKey);
        }

        return $token;
    }

    public function save(string $key, #[SensitiveParameter] AccessToken $token): void
    {
        $item = $this->pool->getItem(CacheKey::of($key));
        $item->set(CachedAccessToken::toArray($token));
        $item->expiresAt($token->expiresAt()->toDateTimeImmutable());
        $this->pool->save($item);
    }

    public function delete(string $key): void
    {
        $this->pool->deleteItem(CacheKey::of($key));
    }
}
