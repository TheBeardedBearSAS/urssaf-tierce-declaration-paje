<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Testing;

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ApiErrors;
use Throwable;

/**
 * FIFO queues, per operation, of the failures to throw, the refusals to answer and the answers to lose.
 *
 * @internal
 */
final class SimulatedScript
{
    /** @var array<string, list<Throwable>> */
    private array $failures = [];

    /** @var array<string, list<ApiErrors>> */
    private array $refusals = [];

    /** @var array<string, int> */
    private array $lostAnswers = [];

    public function fail(Operation $operation, Throwable $failure): void
    {
        $this->failures[$operation->value][] = $failure;
    }

    public function refuse(Operation $operation, ApiErrors $errors): void
    {
        $this->refusals[$operation->value][] = $errors;
    }

    public function loseAnswer(Operation $operation): void
    {
        $this->lostAnswers[$operation->value] = ($this->lostAnswers[$operation->value] ?? 0) + 1;
    }

    /**
     * The next failure of the operation, consumed.
     */
    public function nextFailure(Operation $operation): ?Throwable
    {
        $queue = $this->failures[$operation->value] ?? [];

        $failure = array_shift($queue);
        $this->failures[$operation->value] = $queue;

        return $failure;
    }

    /**
     * The next refusal of the operation, consumed.
     */
    public function nextRefusal(Operation $operation): ?ApiErrors
    {
        $queue = $this->refusals[$operation->value] ?? [];

        $refusal = array_shift($queue);
        $this->refusals[$operation->value] = $queue;

        return $refusal;
    }

    /**
     * Whether the answer of this call is lost, consumed.
     */
    public function takeLostAnswer(Operation $operation): bool
    {
        $count = $this->lostAnswers[$operation->value] ?? 0;
        if ($count === 0) {
            return false;
        }

        $this->lostAnswers[$operation->value] = $count - 1;

        return true;
    }
}
