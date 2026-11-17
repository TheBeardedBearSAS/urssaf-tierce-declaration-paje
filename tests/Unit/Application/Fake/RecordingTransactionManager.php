<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake;

use Override;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\TransactionManager;
use Throwable;

/**
 * Journal-only transaction fake: it writes "begin", then "commit" or "failed" in the journal and restores nothing. "failed"
 * means that the operation threw, not that anything was rolled back: use {@see SnapshottingTransactionManager} to test a
 * rollback.
 */
final class RecordingTransactionManager implements TransactionManager
{
    private int $committed = 0;

    public function __construct(private readonly Journal $journal) {}

    /**
     * @template T
     *
     * @param callable(): T $operation
     *
     * @param-immediately-invoked-callable $operation
     *
     * @return T
     */
    #[Override]
    public function transactional(callable $operation): mixed
    {
        $this->journal->add('begin');

        try {
            $result = $operation();
        } catch (Throwable $exception) {
            $this->journal->add('failed');

            throw $exception;
        }
        $this->journal->add('commit');
        ++$this->committed;

        return $result;
    }

    public function committed(): int
    {
        return $this->committed;
    }
}
