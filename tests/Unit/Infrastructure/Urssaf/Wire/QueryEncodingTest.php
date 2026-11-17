<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\QueryEncoding;

final class QueryEncodingTest extends TestCase
{
    public function testMandateQueryOfTheOfficialExample(): void
    {
        static::assertSame(
            'numeroPaje=Y2926822480004&dateNaissance=1988-01-30',
            QueryEncoding::build(['numeroPaje' => 'Y2926822480004', 'dateNaissance' => '1988-01-30']),
        );
    }

    public function testNoParameterGivesAnEmptyQuery(): void
    {
        static::assertSame('', QueryEncoding::build([]));
        static::assertSame('', QueryEncoding::build(['a' => null]));
    }

    public function testNullParametersAreDropped(): void
    {
        static::assertSame('a=1&c=3', QueryEncoding::build(['a' => '1', 'b' => null, 'c' => '3']));
    }

    public function testEmptyStringIsKept(): void
    {
        static::assertSame('a=', QueryEncoding::build(['a' => '']));
    }

    public function testEncodingIsRfc3986(): void
    {
        static::assertSame('a=b%20c', QueryEncoding::build(['a' => 'b c']));
        static::assertSame('a=%26%3D%2B%23%C3%A9', QueryEncoding::build(['a' => '&=+#é']));
        static::assertSame('a=-._~', QueryEncoding::build(['a' => '-._~']));
        static::assertSame('a%20b=1', QueryEncoding::build(['a b' => '1']));
    }
}
