<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory;

use stdClass;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use WeakMap;

/**
 * Optimistic concurrency of an in-memory repository (ADR 0006, ADR 0014 decision 4): the counterpart of the version
 * column of the Doctrine repositories.
 *
 * The repository keeps one version per stored aggregate. Every copy it hands out (and every instance it saves) remembers
 * the version it was read at, per instance (the aggregates carry no accessor of their persistence token). Saving an
 * instance whose version is not the stored one any more, because another writer saved, claimed or deleted the
 * aggregate since it was read (or created the same identifier since), is refused. A new aggregate is an instance that
 * was never read nor saved, for an identifier nobody stored yet.
 *
 * @internal
 */
final class VersionTokens
{
    /** @var array<string, stdClass> stored version (an opaque token, compared by identity) by aggregate id */
    private array $stored = [];

    /** @var WeakMap<object, stdClass> version each live instance was read or saved at */
    private WeakMap $held;

    /**
     * @param non-empty-string $code    violation code of a stale save (for example "mandate.concurrent_modification")
     * @param non-empty-string $message
     */
    public function __construct(
        private readonly string $code,
        private readonly string $message,
    ) {
        $this->held = new WeakMap();
    }

    /**
     * The instance is a copy of what is stored now (the aggregate must be stored): it can be saved.
     *
     * @template T of object
     *
     * @param T $copy
     *
     * @return T
     */
    public function track(object $copy, string $id): object
    {
        $this->held[$copy] = $this->stored[$id];

        return $copy;
    }

    /**
     * @throws InvariantViolation the aggregate changed (or was created, or erased) since this instance was read
     */
    public function assertCurrent(object $aggregate, string $id): void
    {
        if (($this->stored[$id] ?? null) !== ($this->held[$aggregate] ?? null)) {
            throw InvariantViolation::because($this->code, $this->message);
        }
    }

    /**
     * Stores the instance (after {@see self::assertCurrent()}): a new version, which the instance now holds.
     */
    public function saved(object $aggregate, string $id): void
    {
        $version = new stdClass();
        $this->stored[$id] = $version;
        $this->held[$aggregate] = $version;
    }

    /**
     * The stored aggregate changed without a save of an instance (claim).
     */
    public function bump(string $id): void
    {
        $this->stored[$id] = new stdClass();
    }

    public function forget(string $id): void
    {
        unset($this->stored[$id]);
    }
}
