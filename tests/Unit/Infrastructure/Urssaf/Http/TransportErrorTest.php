<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Http;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Http\TransportError;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Fake\TransportFailure;

final class TransportErrorTest extends TestCase
{
    public function testItKeepsTheClassOfTheClientExceptionOnly(): void
    {
        $original = new TransportFailure('Connection refused for https://h/salaries?nir=FICTIVE&nom=FICTIF', 7);

        $error = TransportError::fromClientException($original);

        static::assertSame('The PSR-18 client failed (' . TransportFailure::class . ').', $error->getMessage());
        static::assertStringNotContainsString('salaries', $error->getMessage());
        static::assertStringNotContainsString('FICTIF', $error->getMessage());
        static::assertSame(0, $error->getCode());
        static::assertNull($error->getPrevious());
    }
}
