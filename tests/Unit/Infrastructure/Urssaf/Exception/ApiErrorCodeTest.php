<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Exception;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Message\NonBlockingCode;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception\ApiErrorCode;

/**
 * @mago-expect lint:too-many-methods
 */
final class ApiErrorCodeTest extends TestCase
{
    /**
     * Every code of api-reference §3.6, verbatim.
     *
     * @return iterable<string, array{string, ApiErrorCode}>
     */
    public static function provideCatalogue(): iterable
    {
        yield 'generic PARAM_INVALIDE' => ['PARAM_INVALIDE', ApiErrorCode::InvalidParameter];
        yield 'generic ER_API_020_0000' => ['ER_API_020_0000', ApiErrorCode::BirthDateMismatch];
        yield 'generic ER_API_020_0010' => ['ER_API_020_0010', ApiErrorCode::EmployerNotFound];
        yield 'generic PE_INEXISTANT' => ['PE_INEXISTANT', ApiErrorCode::EmployerMissing];
        yield 'generic MANDAT_INEXISTANT' => ['MANDAT_INEXISTANT', ApiErrorCode::MandateMissing];
        yield 'generic COMPTE_DEJA_MANDATE' => ['COMPTE_DEJA_MANDATE', ApiErrorCode::MandateHeldElsewhere];
        yield 'generic ERR_TECHNIQUE' => ['ERR_TECHNIQUE', ApiErrorCode::TechnicalError];
        yield 'generic ERREUR_TECHNIQUE' => ['ERREUR_TECHNIQUE', ApiErrorCode::HourlyRateTechnicalError];
        yield 'blocking ER_API_DECLA_0000' => ['ER_API_DECLA_0000', ApiErrorCode::DeclarationTooEarly];
        yield 'blocking ER_API_DECLA_0001' => ['ER_API_DECLA_0001', ApiErrorCode::DeclarationNoLongerAllowed];
        yield 'blocking ER_API_DECLA_0002' => ['ER_API_DECLA_0002', ApiErrorCode::DeclarationLimitReached];
        yield 'blocking ER_API_DECLA_00020' => ['ER_API_DECLA_00020', ApiErrorCode::HomeCareDeclarationExists];
        yield 'blocking ER_API_DECLA_00040' => ['ER_API_DECLA_00040', ApiErrorCode::OneChildMaximum];
        yield 'blocking ER_API_DECLA_00071' => ['ER_API_DECLA_00071', ApiErrorCode::EmploymentPeriodInvalid];
        yield 'blocking ER_API_DECLA_00072' => ['ER_API_DECLA_00072', ApiErrorCode::EmploymentPeriodTooOld];
        yield 'blocking ER_API_DECLA_00100' => ['ER_API_DECLA_00100', ApiErrorCode::EmployeeNotAssociated];
        yield 'blocking ER_API_021_0010' => ['ER_API_021_0010', ApiErrorCode::EmployeeBirthDateMismatch];
        yield 'blocking ER_API_050_0010' => ['ER_API_050_0010', ApiErrorCode::NoActivityDays];
        yield 'blocking ER_API_050_0015' => ['ER_API_050_0015', ApiErrorCode::HourlyRateRequired];
        yield 'blocking ER_API_050_0020' => ['ER_API_050_0020', ApiErrorCode::ChildminderNetSalaryVeryHigh];
        yield 'blocking ER_API_050_0021' => ['ER_API_050_0021', ApiErrorCode::ChildminderNetSalaryInconsistent];
        yield 'blocking ER_API_050_0022' => ['ER_API_050_0022', ApiErrorCode::ChildminderNetSalaryTooLow];
        yield 'blocking ER_API_050_0030' => ['ER_API_050_0030', ApiErrorCode::HoursInconsistent];
        yield 'blocking ER_API_050_0031' => ['ER_API_050_0031', ApiErrorCode::ChildminderNormalHoursExceeded];
        yield 'blocking ER_API_051_0031' => ['ER_API_051_0031', ApiErrorCode::HomeCareNormalHoursExceeded];
        yield 'blocking ER_API_050_0050' => ['ER_API_050_0050', ApiErrorCode::ConsecutiveDaysExceeded];
        yield 'blocking ER_API_050_0051' => ['ER_API_050_0051', ApiErrorCode::HandicapDaysInvalid];
        yield 'blocking ER_API_050_0060' => ['ER_API_050_0060', ApiErrorCode::ContractEndChildMismatch];
        yield 'blocking ER_API_050_0070' => ['ER_API_050_0070', ApiErrorCode::ChildminderDeclarationExists];
        yield 'blocking ER_API_050_0080' => ['ER_API_050_0080', ApiErrorCode::ChildHoursExceeded];
        yield 'blocking ER_API_051_0000' => ['ER_API_051_0000', ApiErrorCode::HomeCareHourlyRateVeryHigh];
        yield 'blocking ER_API_051_0010' => ['ER_API_051_0010', ApiErrorCode::HomeCareNetSalaryVeryHigh];
        yield 'blocking ER_API_051_0020' => ['ER_API_051_0020', ApiErrorCode::OvertimeHoursInconsistent];
        yield 'blocking ER_API_051_0040' => ['ER_API_051_0040', ApiErrorCode::HomeCareHourlyRateBelowMinimumWage];
        yield 'non-blocking ER_API_020_NB_0000' => ['ER_API_020_NB_0000', ApiErrorCode::EmployerNotAuthorised];
        yield 'non-blocking ER_API_020_NB_0010' => ['ER_API_020_NB_0010', ApiErrorCode::EmployerBankDetailsInvalid];
        yield 'non-blocking ER_API_021_NB_0000' => ['ER_API_021_NB_0000', ApiErrorCode::EmployeeNotVerified];
        yield 'non-blocking ER_API_022_NB_0000' => ['ER_API_022_NB_0000', ApiErrorCode::ChildUnknown];
        yield 'non-blocking ER_API_041_NB_0010' => ['ER_API_041_NB_0010', ApiErrorCode::ChildNoLongerEligible];
        yield 'non-blocking ER_API_041_NB_0020' => ['ER_API_041_NB_0020', ApiErrorCode::CalculationModeInconsistent];
        yield 'non-blocking ER_API_023_NB_0000' => ['ER_API_023_NB_0000', ApiErrorCode::EmployeeAlreadyAssociated];
        yield 'non-blocking ER_API_023_DATE_AGREMENT_NB_0000' => ['ER_API_023_DATE_AGREMENT_NB_0000', ApiErrorCode::AgreementYearInconsistentWithBirth];
        yield 'non-blocking ER_API_023_DATE_AGREMENT_NB_0010' => ['ER_API_023_DATE_AGREMENT_NB_0010', ApiErrorCode::AgreementEndDateNotInFuture];
        yield 'non-blocking ER_AG_NB_00000' => ['ER_AG_NB_00000', ApiErrorCode::AgreementDateIncorrect];
        yield 'non-blocking ER_AG_NB_00010' => ['ER_AG_NB_00010', ApiErrorCode::AgreementExpiring];
        yield 'non-blocking ER_API_050_NB_0016' => ['ER_API_050_NB_0016', ApiErrorCode::ChildminderHourlyRateCapped];
        yield 'non-blocking ER_API_051_NB_0016' => ['ER_API_051_NB_0016', ApiErrorCode::HomeCareHourlyRateCapped];
        yield 'non-blocking ER_API_DECLA_NB_0010' => ['ER_API_DECLA_NB_0010', ApiErrorCode::SalaryGapWithPreviousDeclarations];
    }

    #[DataProvider('provideCatalogue')]
    public function testEveryDocumentedCodeIsInTheEnumWithItsWireValue(string $wire, ApiErrorCode $expected): void
    {
        static::assertSame($wire, $expected->value);
        static::assertSame($expected, ApiErrorCode::fromCode($wire));
    }

    public function testTheCatalogueHasNoOtherCase(): void
    {
        static::assertCount(\count(iterator_to_array(self::provideCatalogue(), false)), ApiErrorCode::cases());
        static::assertCount(48, ApiErrorCode::cases());
    }

    public function testNonBlockingFlagMatchesTheDomainNonBlockingCodes(): void
    {
        $nonBlocking = array_map(static fn(ApiErrorCode $code): string => $code->value, array_filter(
            ApiErrorCode::cases(),
            static fn(ApiErrorCode $code): bool => $code->isNonBlocking(),
        ));

        static::assertCount(14, $nonBlocking);
        static::assertEqualsCanonicalizing(array_map(static fn(NonBlockingCode $code): string => $code->value, NonBlockingCode::cases()), $nonBlocking);
        static::assertFalse(ApiErrorCode::TechnicalError->isNonBlocking());
        static::assertFalse(ApiErrorCode::ChildminderDeclarationExists->isNonBlocking());
        static::assertTrue(ApiErrorCode::AgreementExpiring->isNonBlocking());
    }

    /**
     * @return iterable<string, array{string, ApiErrorCode}>
     */
    public static function provideNormalisedCodes(): iterable
    {
        yield 'trailing space (PDF)' => ['PE_INEXISTANT ', ApiErrorCode::EmployerMissing];
        yield 'leading space' => [' PARAM_INVALIDE', ApiErrorCode::InvalidParameter];
        yield 'surrounding newline and tab' => ["\tERR_TECHNIQUE\n", ApiErrorCode::TechnicalError];
        yield 'broken across lines (PDF)' => ["ER_API_050_00\n 10", ApiErrorCode::NoActivityDays];
        yield 'inner space' => ['ER_API_DECLA_ 0000', ApiErrorCode::DeclarationTooEarly];
    }

    #[DataProvider('provideNormalisedCodes')]
    public function testFromCodeToleratesWhitespace(string $raw, ApiErrorCode $expected): void
    {
        static::assertSame($expected, ApiErrorCode::fromCode($raw));
    }

    public function testNormaliseRemovesEveryWhitespace(): void
    {
        static::assertSame('AB_C', ApiErrorCode::normalise(" A B\t_\nC "));
        static::assertSame('', ApiErrorCode::normalise(" \n"));
    }

    /**
     * @return iterable<string, array{string, ApiErrorCode}>
     */
    public static function provideDeclaFamilyTolerance(): iterable
    {
        yield '0020 is 00020' => ['ER_API_DECLA_0020', ApiErrorCode::HomeCareDeclarationExists];
        yield '0040 is 00040' => ['ER_API_DECLA_0040', ApiErrorCode::OneChildMaximum];
        yield '0071 is 00071' => ['ER_API_DECLA_0071', ApiErrorCode::EmploymentPeriodInvalid];
        yield '0072 is 00072' => ['ER_API_DECLA_0072', ApiErrorCode::EmploymentPeriodTooOld];
        yield '0100 is 00100' => ['ER_API_DECLA_0100', ApiErrorCode::EmployeeNotAssociated];
        yield '00000 is 0000' => ['ER_API_DECLA_00000', ApiErrorCode::DeclarationTooEarly];
        yield '00001 is 0001' => ['ER_API_DECLA_00001', ApiErrorCode::DeclarationNoLongerAllowed];
        yield '00002 is 0002' => ['ER_API_DECLA_00002', ApiErrorCode::DeclarationLimitReached];
        yield 'padded with whitespace' => [" ER_API_DECLA_0020\n", ApiErrorCode::HomeCareDeclarationExists];
    }

    #[DataProvider('provideDeclaFamilyTolerance')]
    public function testDeclaFamilyIsRecognisedWithTheOtherDigitCount(string $raw, ApiErrorCode $expected): void
    {
        static::assertSame($expected, ApiErrorCode::fromCode($raw));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideUnknownCodes(): iterable
    {
        yield 'empty' => [''];
        yield 'unknown' => ['SOMETHING_NEW'];
        yield 'lower case' => ['param_invalide'];
        yield 'unknown decla number' => ['ER_API_DECLA_0003'];
        yield 'unknown decla number 5 digits' => ['ER_API_DECLA_00003'];
        yield 'decla with 3 digits' => ['ER_API_DECLA_020'];
        yield 'decla with 6 digits' => ['ER_API_DECLA_000020'];
        yield 'decla with a suffix' => ['ER_API_DECLA_0020X'];
        yield 'decla with a prefix' => ['XER_API_DECLA_0020'];
        yield 'decla without digits' => ['ER_API_DECLA_'];
        yield 'other family is not tolerated' => ['ER_API_050_00010'];
        yield 'other family 3 digits' => ['ER_API_050_010'];
    }

    #[DataProvider('provideUnknownCodes')]
    public function testUnknownCodesGiveNull(string $raw): void
    {
        static::assertNull(ApiErrorCode::fromCode($raw));
    }
}
