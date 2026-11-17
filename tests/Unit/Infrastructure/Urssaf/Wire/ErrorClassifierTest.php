<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\RejectionKind;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception\ApiErrorCode;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\ErrorClassifier;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\ErrorPayloadParser;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;

/**
 * @mago-expect lint:too-many-methods
 */
final class ErrorClassifierTest extends TestCase
{
    /**
     * @return iterable<string, array{string, RejectionKind}>
     */
    public static function provideFixtureKinds(): iterable
    {
        yield 'error_24h_days_exceeded_0050' => ['error_24h_days_exceeded_0050', RejectionKind::BusinessRule];
        yield 'error_birth_date_mismatch' => ['error_birth_date_mismatch', RejectionKind::BirthDateMismatch];
        yield 'error_childcare_hours_exceeded_0080' => ['error_childcare_hours_exceeded_0080', RejectionKind::BusinessRule];
        yield 'error_compte_deja_mandate' => ['error_compte_deja_mandate', RejectionKind::MandateHeldElsewhere];
        yield 'error_contract_end_child_0060' => ['error_contract_end_child_0060', RejectionKind::BusinessRule];
        yield 'error_duplicate_declaration_0070' => ['error_duplicate_declaration_0070', RejectionKind::DuplicateDeclaration];
        yield 'error_employer_not_found' => ['error_employer_not_found', RejectionKind::EmployerNotFound];
        yield 'error_handicap_days_invalid_0051' => ['error_handicap_days_invalid_0051', RejectionKind::BusinessRule];
        yield 'error_mandat_inexistant' => ['error_mandat_inexistant', RejectionKind::MandateMissing];
        yield 'error_one_child_max_00040' => ['error_one_child_max_00040', RejectionKind::BusinessRule];
        yield 'error_param_invalide' => ['error_param_invalide', RejectionKind::InvalidParameter];
        yield 'error_pe_inexistant' => ['error_pe_inexistant', RejectionKind::EmployerNotFound];
        yield 'error_period_invalid_00071' => ['error_period_invalid_00071', RejectionKind::DeclarationWindow];
        yield 'error_period_invalid_00072' => ['error_period_invalid_00072', RejectionKind::DeclarationWindow];
        yield 'error_technical' => ['error_technical', RejectionKind::Technical];
    }

    #[DataProvider('provideFixtureKinds')]
    public function testEveryOfficialErrorFixtureIsClassified(string $name, RejectionKind $expected): void
    {
        $errors = (new ErrorPayloadParser())->parseBody(Fixtures::load($name));

        static::assertSame($expected, ErrorClassifier::classify($errors));
    }

    public function testEveryErrorFixtureIsCovered(): void
    {
        static::assertSame(
            Fixtures::names('error_'),
            array_map(static fn(array $row): string => $row[0], iterator_to_array(self::provideFixtureKinds(), false)),
        );
    }

    /**
     * Kind of every code of the catalogue that has a dedicated kind; every other one is a business rule.
     *
     * @return array<string, RejectionKind>
     */
    private static function dedicatedKinds(): array
    {
        return [
            'ER_API_050_0070' => RejectionKind::DuplicateDeclaration,
            'ER_API_DECLA_00020' => RejectionKind::DuplicateDeclaration,
            'ER_API_DECLA_0000' => RejectionKind::DeclarationWindow,
            'ER_API_DECLA_0001' => RejectionKind::DeclarationWindow,
            'ER_API_DECLA_00071' => RejectionKind::DeclarationWindow,
            'ER_API_DECLA_00072' => RejectionKind::DeclarationWindow,
            'ER_API_DECLA_00100' => RejectionKind::EmployeeNotAssociated,
            'ER_API_020_0010' => RejectionKind::EmployerNotFound,
            'PE_INEXISTANT' => RejectionKind::EmployerNotFound,
            'ER_API_020_0000' => RejectionKind::BirthDateMismatch,
            'ER_API_021_0010' => RejectionKind::EmployeeBirthDateMismatch,
            'MANDAT_INEXISTANT' => RejectionKind::MandateMissing,
            'COMPTE_DEJA_MANDATE' => RejectionKind::MandateHeldElsewhere,
            'PARAM_INVALIDE' => RejectionKind::InvalidParameter,
            'ERR_TECHNIQUE' => RejectionKind::Technical,
        ];
    }

    public function testEveryCodeOfTheCatalogueIsClassified(): void
    {
        $dedicated = self::dedicatedKinds();

        foreach (ApiErrorCode::cases() as $code) {
            static::assertSame(
                $dedicated[$code->value] ?? RejectionKind::BusinessRule,
                ErrorClassifier::classify(ApiErrors::of(ApiError::of($code->value, null, null))),
                $code->value,
            );
        }
    }

    public function testDedicatedCodesAreAllInTheCatalogue(): void
    {
        $values = array_map(static fn(ApiErrorCode $code): string => $code->value, ApiErrorCode::cases());

        static::assertSame([], array_diff(array_keys(self::dedicatedKinds()), $values));
    }

    public function testNoErrorOrOnlyUnknownCodesIsUnknown(): void
    {
        static::assertSame(RejectionKind::Unknown, ErrorClassifier::classify(ApiErrors::none()));
        static::assertSame(RejectionKind::Unknown, ErrorClassifier::classify(ApiErrors::of(ApiError::of('SOMETHING_NEW', null, null))));
        static::assertSame(
            RejectionKind::Unknown,
            ErrorClassifier::classify(ApiErrors::of(ApiError::of('A', null, null), ApiError::of('B', null, null))),
        );
    }

    public function testTheOtherDigitCountOfTheDeclaFamilyIsRecognised(): void
    {
        static::assertSame(RejectionKind::DuplicateDeclaration, self::classifyCodes('ER_API_DECLA_0020'));
        static::assertSame(RejectionKind::DeclarationWindow, self::classifyCodes('ER_API_DECLA_00071'));
        static::assertSame(RejectionKind::DeclarationWindow, self::classifyCodes('ER_API_DECLA_0071'));
        static::assertSame(RejectionKind::DeclarationWindow, self::classifyCodes('ER_API_DECLA_00001'));
        static::assertSame(RejectionKind::EmployeeNotAssociated, self::classifyCodes('ER_API_DECLA_0100'));
        static::assertSame(RejectionKind::Unknown, self::classifyCodes('ER_API_DECLA_0003'));
    }

    public function testAKnownCodeBesideAnUnknownOneIsStillClassified(): void
    {
        static::assertSame(RejectionKind::BusinessRule, self::classifyCodes('SOMETHING_NEW', 'ER_API_050_0010'));
        static::assertSame(RejectionKind::BusinessRule, self::classifyCodes('ER_API_050_0010', 'SOMETHING_NEW'));
        static::assertSame(RejectionKind::Technical, self::classifyCodes('SOMETHING_NEW', 'ERR_TECHNIQUE'));
    }

    /**
     * Highest priority first, one representative code each.
     *
     * @return list<array{RejectionKind, string}>
     */
    private static function priorityLadder(): array
    {
        return [
            [RejectionKind::DuplicateDeclaration, 'ER_API_050_0070'],
            [RejectionKind::DeclarationWindow, 'ER_API_DECLA_0000'],
            [RejectionKind::EmployeeNotAssociated, 'ER_API_DECLA_00100'],
            [RejectionKind::EmployerNotFound, 'ER_API_020_0010'],
            [RejectionKind::BirthDateMismatch, 'ER_API_020_0000'],
            [RejectionKind::EmployeeBirthDateMismatch, 'ER_API_021_0010'],
            [RejectionKind::MandateMissing, 'MANDAT_INEXISTANT'],
            [RejectionKind::MandateHeldElsewhere, 'COMPTE_DEJA_MANDATE'],
            [RejectionKind::InvalidParameter, 'PARAM_INVALIDE'],
            [RejectionKind::Technical, 'ERR_TECHNIQUE'],
            [RejectionKind::BusinessRule, 'ER_API_050_0010'],
        ];
    }

    /**
     * @return iterable<string, array{string, RejectionKind, string, RejectionKind}>
     */
    public static function providePriorityPairs(): iterable
    {
        $ladder = self::priorityLadder();
        foreach ($ladder as $high => [$highKind, $highCode]) {
            foreach (\array_slice($ladder, $high + 1) as [$lowKind, $lowCode]) {
                yield $highKind->value . ' over ' . $lowKind->value => [$highCode, $highKind, $lowCode, $lowKind];
            }
        }
    }

    #[DataProvider('providePriorityPairs')]
    public function testTheHighestPriorityKindWinsWhateverTheOrder(string $highCode, RejectionKind $highKind, string $lowCode, RejectionKind $lowKind): void
    {
        static::assertSame($highKind, self::classifyCodes($highCode, $lowCode));
        static::assertSame($highKind, self::classifyCodes($lowCode, $highCode));
        static::assertSame($lowKind, self::classifyCodes($lowCode));
    }

    public function testEveryNonUnknownKindIsInTheLadder(): void
    {
        $ladder = array_map(static fn(array $row): RejectionKind => $row[0], self::priorityLadder());

        static::assertCount(11, $ladder);
        static::assertSame(
            [],
            array_filter(
                RejectionKind::cases(),
                static fn(RejectionKind $kind): bool => $kind !== RejectionKind::Unknown && !\in_array($kind, $ladder, true),
            ),
        );
    }

    public function testTheHourlyRateRejectionIsADeterministicBusinessRuleNeverTechnical(): void
    {
        static::assertSame(RejectionKind::BusinessRule, self::classifyCodes('ERREUR_TECHNIQUE'));
        static::assertSame(RejectionKind::Technical, self::classifyCodes('ERR_TECHNIQUE', 'ERREUR_TECHNIQUE'));
    }

    public function testNonBlockingCodesAreBusinessRules(): void
    {
        static::assertSame(RejectionKind::BusinessRule, self::classifyCodes('ER_API_020_NB_0000'));
    }

    private static function classifyCodes(string ...$codes): RejectionKind
    {
        return ErrorClassifier::classify(ApiErrors::of(...array_map(
            static fn(string $code): ApiError => ApiError::of($code, null, null),
            $codes,
        )));
    }
}
