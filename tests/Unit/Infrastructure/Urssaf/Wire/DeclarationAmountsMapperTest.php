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
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\DeclarationAmountsMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\Payload;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\WireAssertions;

final class DeclarationAmountsMapperTest extends TestCase
{
    private const FULL = '{"mntCotisations":1.01,"mntCotiPriseEnChargeOdpf":2.02,"mntCotiEmplAcharge":3.03,'
        . '"mntSalaireNetImposable":4.04,"txPas":7.5,"mntImpotPas":5.05,"mntSalaireNetApayerApresPas":6.06,'
        . '"mntEmplCmgRemuneration":7.07,"mntEmplAverserSalarie":8.08,"mntAverserParPaje":9.09,'
        . '"mntApreleverParPaje":10.1,"dtPrelevementPrevisionnelle":"2026-11-05","dtVersementPrevisionnelle":"2026-11-10"}';

    public function testEveryFieldIsReadFromItsWireName(): void
    {
        $amounts = DeclarationAmountsMapper::map(JsonDecoder::decode(self::FULL));

        static::assertSame('1.01', $amounts->contributions()?->toDecimalString());
        static::assertSame('2.02', $amounts->coveredByFamilyBenefits()?->toDecimalString());
        static::assertSame('3.03', $amounts->employerShare()?->toDecimalString());
        static::assertSame('4.04', $amounts->withholding()->taxableNetSalary()?->toDecimalString());
        static::assertSame('7.5', $amounts->withholding()->rate()?->toString());
        static::assertSame('5.05', $amounts->withholding()->taxWithheld()?->toDecimalString());
        static::assertSame('6.06', $amounts->withholding()->netPayableAfterTax()?->toDecimalString());
        static::assertSame('7.07', $amounts->cmgRemuneration()?->toDecimalString());
        static::assertSame('8.08', $amounts->payableToEmployee()?->toDecimalString());
        static::assertSame('9.09', $amounts->payableByPaje()?->toDecimalString());
        static::assertSame('10.10', $amounts->debitedByPaje()?->toDecimalString());
        static::assertSame('2026-11-05', $amounts->plannedDebitDate()?->toString());
        static::assertSame('2026-11-10', $amounts->plannedPaymentDate()?->toString());
    }

    public function testTheOfficialExampleWithValidDatesIsMapped(): void
    {
        $declaration = JsonDecoder::decode(Fixtures::load('declare_response_valid'))->requireObject('declaration');
        $amounts = DeclarationAmountsMapper::map($declaration);

        static::assertSame('0.00', $amounts->contributions()?->toDecimalString());
        static::assertSame('0', $amounts->withholding()->rate()?->toString());
        static::assertSame('2020-01-01', $amounts->plannedDebitDate()?->toString());
        static::assertSame('2020-01-31', $amounts->plannedPaymentDate()?->toString());
    }

    public function testTheOfficialPlaceholderDatesAreRefused(): void
    {
        $declaration = JsonDecoder::decode(Fixtures::load('declare_response'))->requireObject('declaration');

        WireAssertions::assertUnexpected(
            'Unexpected URSSAF response: field "dtPrelevementPrevisionnelle" is not a Y-m-d date.',
            static fn(): mixed => DeclarationAmountsMapper::map($declaration),
        );
    }

    public function testThePlannedPaymentDateIsRefusedWhenInvalid(): void
    {
        WireAssertions::assertUnexpected(
            'Unexpected URSSAF response: field "dtVersementPrevisionnelle" is not a Y-m-d date.',
            static fn(): mixed => DeclarationAmountsMapper::map(JsonDecoder::decode('{"dtVersementPrevisionnelle":"2026-11-31"}')),
        );
        WireAssertions::assertUnexpected(
            'Unexpected URSSAF response: field "dtVersementPrevisionnelle" is not a string.',
            static fn(): mixed => DeclarationAmountsMapper::map(JsonDecoder::decode('{"dtVersementPrevisionnelle":20261110}')),
        );
    }

    public function testAnEmptyOrAbsentObjectHasNoValue(): void
    {
        $amounts = DeclarationAmountsMapper::map(Payload::empty());

        static::assertNull($amounts->contributions());
        static::assertNull($amounts->coveredByFamilyBenefits());
        static::assertNull($amounts->employerShare());
        static::assertNull($amounts->withholding()->taxableNetSalary());
        static::assertNull($amounts->withholding()->rate());
        static::assertNull($amounts->withholding()->taxWithheld());
        static::assertNull($amounts->withholding()->netPayableAfterTax());
        static::assertNull($amounts->cmgRemuneration());
        static::assertNull($amounts->payableToEmployee());
        static::assertNull($amounts->payableByPaje());
        static::assertNull($amounts->debitedByPaje());
        static::assertNull($amounts->plannedDebitDate());
        static::assertNull($amounts->plannedPaymentDate());
    }

    public function testNullFieldsAreAbsent(): void
    {
        $amounts = DeclarationAmountsMapper::map(JsonDecoder::decode('{"mntCotisations":null,"txPas":null,"dtPrelevementPrevisionnelle":null}'));

        static::assertNull($amounts->contributions());
        static::assertNull($amounts->withholding()->rate());
        static::assertNull($amounts->plannedDebitDate());
    }

    public function testTheRateIsAnExactDecimal(): void
    {
        static::assertSame('0.00001', DeclarationAmountsMapper::map(JsonDecoder::decode('{"txPas":0.00001}'))->withholding()->rate()?->toString());
        static::assertSame('12.345678', DeclarationAmountsMapper::map(JsonDecoder::decode('{"txPas":12.345678}'))->withholding()->rate()?->toString());
        // An integer beyond the integer range arrives as a digit string (JSON_BIGINT_AS_STRING): no bound (ADR 0008).
        static::assertSame('123456789012345678901234567890', DeclarationAmountsMapper::map(JsonDecoder::decode('{"txPas":123456789012345678901234567890}'))->withholding()->rate()?->toString());
    }

    public function testAMalformedAmountIsRefusedNamingTheFieldOnly(): void
    {
        foreach (
            [
                'mntCotisations',
                'mntCotiPriseEnChargeOdpf',
                'mntCotiEmplAcharge',
                'mntSalaireNetImposable',
                'mntImpotPas',
                'mntSalaireNetApayerApresPas',
                'mntEmplCmgRemuneration',
                'mntEmplAverserSalarie',
                'mntAverserParPaje',
                'mntApreleverParPaje',
            ] as $field
        ) {
            WireAssertions::assertUnexpected(
                'Unexpected URSSAF response: field "' . $field . '" is not a number.',
                static fn(): mixed => DeclarationAmountsMapper::map(JsonDecoder::decode('{"' . $field . '":"12,5"}')),
            );
        }
    }

    public function testAMalformedRateIsRefused(): void
    {
        WireAssertions::assertUnexpected(
            'Unexpected URSSAF response: field "txPas" is not a number.',
            static fn(): mixed => DeclarationAmountsMapper::map(JsonDecoder::decode('{"txPas":"7.5"}')),
        );
    }

    public function testAnAmountBeyondTwoDecimalsIsRoundedToTheCent(): void
    {
        static::assertSame(
            '1.24',
            DeclarationAmountsMapper::map(JsonDecoder::decode('{"mntCotisations":1.2351}'))->contributions()?->toDecimalString(),
        );
    }
}
