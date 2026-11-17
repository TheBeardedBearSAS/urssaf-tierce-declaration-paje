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

use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Http\RetryAfter;

final class RetryAfterTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int|null}>
     */
    public static function provideHeaders(): iterable
    {
        yield 'seconds' => ['120', 120];
        yield 'padded' => [' 7 ', 7];
        yield 'max digits' => ['999999999', 999_999_999];
        yield 'zero' => ['0', 0];
        yield 'absent' => ['', null];
        yield 'http date' => ['Wed, 21 Oct 2026 07:28:00 GMT', null];
        yield 'negative' => ['-5', null];
        yield 'decimal' => ['1.5', null];
        yield 'too long' => ['1000000000', null];
        yield 'garbage' => ['abc', null];
    }

    #[DataProvider('provideHeaders')]
    public function testItParsesTheDelayInSeconds(string $header, ?int $expected): void
    {
        $response = new Response(429, $header === '' ? [] : ['Retry-After' => $header]);

        static::assertSame($expected, RetryAfter::seconds($response));
    }

    public function testItIgnoresSurroundingWhitespaceOfTheHeaderLine(): void
    {
        $response = self::createStub(ResponseInterface::class);
        $response->method('getHeaderLine')->willReturn(" \t12\n");

        static::assertSame(12, RetryAfter::seconds($response));
    }
}
