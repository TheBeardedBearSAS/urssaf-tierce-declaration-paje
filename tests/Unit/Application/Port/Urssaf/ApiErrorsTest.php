<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Port\Urssaf;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ApiErrors;

final class ApiErrorsTest extends TestCase
{
    public function testNoneIsEmpty(): void
    {
        $errors = ApiErrors::none();

        static::assertTrue($errors->isEmpty());
        static::assertCount(0, $errors);
        static::assertSame([], $errors->all());
        static::assertSame([], $errors->codes());
        static::assertNull($errors->first());
        static::assertFalse($errors->hasCode('A'));
        static::assertSame([], iterator_to_array($errors, false));
    }

    public function testOfKeepsTheOrder(): void
    {
        $first = ApiError::of('A', 'one', null);
        $second = ApiError::of('B', 'two', 'd');

        $errors = ApiErrors::of($first, $second);

        static::assertFalse($errors->isEmpty());
        static::assertCount(2, $errors);
        static::assertSame([$first, $second], $errors->all());
        static::assertSame(['A', 'B'], $errors->codes());
        static::assertSame($first, $errors->first());
        static::assertSame([$first, $second], iterator_to_array($errors, false));
    }

    public function testHasCodeLooksAtEveryError(): void
    {
        $errors = ApiErrors::of(ApiError::of('A', null, null), ApiError::of('B', null, null));

        static::assertTrue($errors->hasCode('A'));
        static::assertTrue($errors->hasCode('B'));
        static::assertFalse($errors->hasCode('C'));
    }

    public function testASingleErrorIsNotEmpty(): void
    {
        $errors = ApiErrors::of(ApiError::of('A', null, null));

        static::assertFalse($errors->isEmpty());
        static::assertCount(1, $errors);
    }

    public function testOfReindexesKeyedArguments(): void
    {
        $first = ApiError::of('A', null, null);
        $second = ApiError::of('B', null, null);

        $errors = ApiErrors::of(...['x' => $first, 'y' => $second]);

        static::assertSame($first, $errors->first());
        static::assertSame([$first, $second], $errors->all());
        static::assertSame(['A', 'B'], $errors->codes());
    }
}
