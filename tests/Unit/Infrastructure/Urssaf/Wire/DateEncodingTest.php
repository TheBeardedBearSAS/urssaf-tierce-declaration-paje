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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\DateEncoding;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\WireAssertions;

final class DateEncodingTest extends TestCase
{
    public function testEncodeIsDateOnly(): void
    {
        static::assertSame('1988-01-30', DateEncoding::encode(CalendarDate::fromString('1988-01-30')));
        static::assertSame('0001-01-01', DateEncoding::encode(CalendarDate::fromString('0001-01-01')));
    }

    public function testDecodeIsStrict(): void
    {
        static::assertSame('2015-01-12', DateEncoding::decode('2015-01-12', 'dtNaissanceEnfant')->toString());
        static::assertSame('2024-02-29', DateEncoding::decode('2024-02-29', 'dt')->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideRefusedDates(): iterable
    {
        yield 'official placeholder' => ['string'];
        yield 'empty' => [''];
        yield 'time part and Z' => ['2015-01-12T00:00:00Z'];
        yield 'time part and offset' => ['2015-01-12T00:00:00+01:00'];
        yield 'datetime with space' => ['2015-01-12 00:00:00'];
        yield 'single digit day (PDF example)' => ['1990-10-1'];
        yield 'slashes' => ['12/01/2015'];
        yield 'no separators' => ['20150112'];
        yield 'non existing day' => ['2023-02-29'];
        yield 'month 13' => ['2015-13-01'];
        yield 'trailing newline' => ["2015-01-12\n"];
        yield 'leading space' => [' 2015-01-12'];
    }

    #[DataProvider('provideRefusedDates')]
    public function testDecodeRefusesAnythingElse(string $value): void
    {
        $exception = WireAssertions::caught(static fn(): CalendarDate => DateEncoding::decode($value, 'dtVersementPrevisionnelle'));

        static::assertSame('Unexpected URSSAF response: field "dtVersementPrevisionnelle" is not a Y-m-d date.', $exception->getMessage());
        static::assertInstanceOf(InvalidValue::class, $exception->getPrevious());
        static::assertStringNotContainsString($value === '' ? "\0" : $value, $exception->getMessage());
    }

    public function testThePlaceholderOfTheOfficialFixtureIsRefused(): void
    {
        $declaration = JsonDecoder::decode(Fixtures::load('predeclare_response'))->requireObject('declaration');
        $placeholder = $declaration->requireString('dtPrelevementPrevisionnelle');

        static::assertSame('string', $placeholder);
        WireAssertions::assertUnexpected(
            'Unexpected URSSAF response: field "dtPrelevementPrevisionnelle" is not a Y-m-d date.',
            static fn(): CalendarDate => DateEncoding::decode($placeholder, 'dtPrelevementPrevisionnelle'),
        );
    }

    public function testTheOfficialChildBirthDateIsAccepted(): void
    {
        $child = JsonDecoder::decode(Fixtures::load('verify_children_response'))->items('verificationEnfantPe')[0]->requireObject('inputEnfantPe');

        static::assertSame('2015-01-12', DateEncoding::decode($child->requireString('dtNaissanceEnfant'), 'dtNaissanceEnfant')->toString());
    }

    public function testTheRawInputIsSensitiveDateEncoding(): void
    {
        DomainAssertions::assertSensitiveParameters(DateEncoding::class, 'decode', ['value']);
    }
}
