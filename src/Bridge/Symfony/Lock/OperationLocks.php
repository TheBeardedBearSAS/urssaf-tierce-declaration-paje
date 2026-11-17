<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Lock;

use Symfony\Component\Lock\LockFactory;

/**
 * Prevents overlapping runs of the same operation for the same scope (overlapping crons, a cron racing a Messenger
 * worker, two mandate commands of one employer...) with a non-blocking Symfony Lock named
 * "urssaf_paje.<operation>.<scope>" (ADR 0006, ADR 0013).
 *
 * Without a lock factory (symfony/lock not installed, or the "lock" option disabled) every operation just runs: the
 * domain claims (state transitions, conditional updates, optimistic concurrency) remain the last line of defence
 * against a double submission.
 */
final readonly class OperationLocks
{
    public const PREFIX = 'urssaf_paje';

    public function __construct(private ?LockFactory $factory, private float $ttl = 3600.0) {}

    /**
     * False when the lock is disabled or symfony/lock is missing: every operation then runs unlocked.
     */
    public function isEnabled(): bool
    {
        return $this->factory instanceof LockFactory;
    }

    public static function resource(string $operation, string $scope): string
    {
        return \sprintf('%s.%s.%s', self::PREFIX, $operation, $scope);
    }

    /**
     * Runs $action while holding the lock of the operation, or $busy (given the lock name) when another process holds it.
     *
     * @template T
     * @template B
     *
     * @param callable(): T       $action
     * @param callable(string): B $busy
     * @param-immediately-invoked-callable $action
     * @param-immediately-invoked-callable $busy
     *
     * @return T|B
     */
    public function exclusively(string $operation, string $scope, callable $action, callable $busy): mixed
    {
        if (!$this->factory instanceof LockFactory) {
            return $action();
        }
        $resource = self::resource($operation, $scope);
        $lock = $this->factory->createLock($resource, $this->ttl);
        if (!$lock->acquire(false)) {
            return $busy($resource);
        }
        try {
            return $action();
        } finally {
            $lock->release();
        }
    }
}
