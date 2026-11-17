<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Dto;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\BatchItem;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\BatchReport;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\ItemOutcome;

final class BatchReportTest extends TestCase
{
    public function testKeepsItemsInOrderAndCountsThem(): void
    {
        $changed = new BatchItem('account-1', ItemOutcome::Changed);
        $failed = new BatchItem('account-2', ItemOutcome::Failed, ['CODE']);

        $report = BatchReport::of(...['a' => $changed, 'b' => $failed, 'c' => $changed]);

        static::assertSame([$changed, $failed, $changed], $report->items());
        static::assertSame(3, $report->total());
        static::assertSame(2, $report->count(ItemOutcome::Changed));
        static::assertSame(1, $report->count(ItemOutcome::Failed));
        static::assertSame(0, $report->count(ItemOutcome::Unchanged));
        static::assertSame([], $changed->codes);
        static::assertSame(['CODE'], $failed->codes);
    }

    public function testMergesInOrderWithoutChangingTheOriginals(): void
    {
        $first = new BatchItem('account-1', ItemOutcome::Changed);
        $second = new BatchItem('account-2', ItemOutcome::RetryLater);
        $third = new BatchItem('account-3', ItemOutcome::Changed);
        $fourth = new BatchItem('account-4', ItemOutcome::Unchanged);
        $left = BatchReport::of($first, $second);
        $right = BatchReport::of($third, $fourth);

        $merged = $left->merge($right);

        static::assertSame([$first, $second, $third, $fourth], $merged->items());
        static::assertSame(2, $left->total());
        static::assertSame(2, $right->total());
        static::assertSame(0, BatchReport::of()->total());
    }

    public function testOutcomeValuesAreStable(): void
    {
        static::assertSame(
            [
                'declared',
                'rejected',
                'already_declared',
                'outcome_unknown',
                'invalid_credentials',
                'exhausted',
                'not_claimed',
                'replayed',
                'manual_review',
                'changed',
                'unchanged',
                'failed',
                'retry_later',
            ],
            array_map(static fn(ItemOutcome $outcome): string => $outcome->value, ItemOutcome::cases()),
        );
    }
}
