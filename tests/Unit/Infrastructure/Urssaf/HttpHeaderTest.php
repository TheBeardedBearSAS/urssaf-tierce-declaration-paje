<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\HttpHeader;

final class HttpHeaderTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideValidHeaders(): iterable
    {
        yield 'simple' => ['X-Application-Id', 'app-42'];
        yield 'every token symbol' => ["!#$%&'*+.^_`|~-09azAZ", 'v'];
        yield 'value with inner spaces' => ['X-Note', 'a b  c'];
        yield 'one character value' => ['X-Note', '!'];
        yield 'last visible character' => ['X-Note', '~'];
        yield 'contains reserved word as part' => ['X-User-Agent-Extra', 'v'];
    }

    #[DataProvider('provideValidHeaders')]
    public function testItAcceptsValidHeaders(string $name, string $value): void
    {
        $header = HttpHeader::of($name, $value);

        static::assertSame($name, $header->name());
        static::assertSame($value, $header->value());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function provideInvalidHeaders(): iterable
    {
        yield 'empty name' => ['', 'v', HttpHeader::NAME_INVALID];
        yield 'name with space' => ['X Id', 'v', HttpHeader::NAME_INVALID];
        yield 'name with colon' => ['X-Id:', 'v', HttpHeader::NAME_INVALID];
        yield 'name with accent' => ['X-Idé', 'v', HttpHeader::NAME_INVALID];
        yield 'name with trailing line feed' => ["X-Id\n", 'v', HttpHeader::NAME_INVALID];
        yield 'name with leading line feed' => ["\nX-Id", 'v', HttpHeader::NAME_INVALID];
        yield 'authorization' => ['Authorization', 'v', HttpHeader::NAME_RESERVED];
        yield 'accept lower case' => ['accept', 'v', HttpHeader::NAME_RESERVED];
        yield 'content-type' => ['Content-Type', 'v', HttpHeader::NAME_RESERVED];
        yield 'content-length' => ['CONTENT-LENGTH', 'v', HttpHeader::NAME_RESERVED];
        yield 'host' => ['Host', 'v', HttpHeader::NAME_RESERVED];
        yield 'user-agent' => ['User-Agent', 'v', HttpHeader::NAME_RESERVED];
        yield 'empty value' => ['X-Id', '', HttpHeader::VALUE_INVALID];
        yield 'leading space' => ['X-Id', ' v', HttpHeader::VALUE_INVALID];
        yield 'trailing space' => ['X-Id', 'v ', HttpHeader::VALUE_INVALID];
        yield 'only a space' => ['X-Id', ' ', HttpHeader::VALUE_INVALID];
        yield 'carriage return and line feed' => ['X-Id', "a\r\nb", HttpHeader::VALUE_INVALID];
        yield 'trailing line feed' => ['X-Id', "v\n", HttpHeader::VALUE_INVALID];
        yield 'leading line feed' => ['X-Id', "\nv", HttpHeader::VALUE_INVALID];
        yield 'tab' => ['X-Id', "a\tb", HttpHeader::VALUE_INVALID];
        yield 'delete character' => ['X-Id', "a\x7Fb", HttpHeader::VALUE_INVALID];
        yield 'accent' => ['X-Id', 'é', HttpHeader::VALUE_INVALID];
        yield 'name violation comes before the value one' => ['X Id', '', HttpHeader::NAME_INVALID];
        yield 'reserved name comes before the value one' => ['Host', '', HttpHeader::NAME_RESERVED];
    }

    #[DataProvider('provideInvalidHeaders')]
    public function testItRefusesInvalidHeaders(string $name, string $value, string $code): void
    {
        try {
            HttpHeader::of($name, $value);
            static::fail('The header must be refused.');
        } catch (InvalidValue $exception) {
            static::assertSame($code, $exception->violationCode());
        }
    }

    public function testViolationMessagesAreExactAndNeverContainTheValue(): void
    {
        $messages = [];

        foreach ([['X Id', 'secret-1'], ['Host', 'secret-2'], ['X-Id', "secret-3\n"]] as [$name, $value]) {
            try {
                HttpHeader::of($name, $value);
            } catch (InvalidValue $exception) {
                $messages[] = $exception->getMessage();
            }
        }

        static::assertSame([
            'An HTTP header name must be an RFC 9110 token.',
            'This HTTP header is set by the gateway and cannot be configured.',
            'An HTTP header value must be visible ASCII without line breaks.',
        ], $messages);
    }

    public function testValidNameReturnsTheNameAndChecksItOnly(): void
    {
        static::assertSame('X-Request-Id', HttpHeader::validName('X-Request-Id'));

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('This HTTP header is set by the gateway and cannot be configured.');

        HttpHeader::validName('Accept');
    }

    public function testValidNameRefusesAnInvalidToken(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('An HTTP header name must be an RFC 9110 token.');

        HttpHeader::validName('bad name');
    }
}
