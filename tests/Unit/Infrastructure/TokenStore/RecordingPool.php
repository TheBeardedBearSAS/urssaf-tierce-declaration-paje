<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\TokenStore;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * PSR-6 decorator over an array pool that records the keys it was asked to delete.
 */
final class RecordingPool implements CacheItemPoolInterface
{
    /** @var list<string> */
    private array $deleted = [];

    /** @var list<string> */
    private array $saved = [];

    public function __construct(private readonly ArrayAdapter $pool = new ArrayAdapter()) {}

    public function getItem(string $key): CacheItemInterface
    {
        return $this->pool->getItem($key);
    }

    /**
     * @param array<string> $keys
     *
     * @return iterable<mixed, mixed>
     */
    public function getItems(array $keys = []): iterable
    {
        return $this->pool->getItems($keys);
    }

    public function hasItem(string $key): bool
    {
        return $this->pool->hasItem($key);
    }

    public function clear(): bool
    {
        return $this->pool->clear();
    }

    public function deleteItem(string $key): bool
    {
        $this->deleted[] = $key;

        return $this->pool->deleteItem($key);
    }

    /**
     * @param array<string> $keys
     */
    public function deleteItems(array $keys): bool
    {
        return $this->pool->deleteItems($keys);
    }

    public function save(CacheItemInterface $item): bool
    {
        $this->saved[] = $item->getKey();

        return $this->pool->save($item);
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->pool->saveDeferred($item);
    }

    public function commit(): bool
    {
        return $this->pool->commit();
    }

    /**
     * @return list<string>
     */
    public function deleted(): array
    {
        return $this->deleted;
    }

    /**
     * @return list<string>
     */
    public function saved(): array
    {
        return $this->saved;
    }
}
